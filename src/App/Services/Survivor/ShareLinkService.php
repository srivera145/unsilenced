<?php

namespace Keel\App\Services\Survivor;

use Keel\App\Support\Config;
use Keel\Core\Database;
use Keel\Core\Env;

/**
 * Share links: how a survivor gives chosen evidence files to someone (an
 * attorney, an advocate), originals included, on their terms.
 *
 * - The token is 32 random bytes, shown to them once. Only its SHA-256 is
 *   stored.
 * - It travels in the URL fragment (/share#token). Browsers never send the
 *   fragment to the server, so the token cannot land in any access log, proxy
 *   log or Referer; the page's script posts it instead.
 * - Each link expires (24 hours, 7 days or 30 days), may need a passcode
 *   (Argon2id), stops working after too many wrong passcodes, and can be
 *   revoked at once (the row is deleted).
 * - last_opened_at is the only thing recorded about an opening: a time.
 */
final class ShareLinkService
{
    private const LABEL_CONTEXT = 'share_links.label';

    /** @return array{token: string, id: int} */
    public function create(int $caseId, array $fileIds, ?string $label, string $expiry, ?string $passcode): array
    {
        $choices = (array) Config::get('survivor_reports.share_link_expiry', []);
        if (!isset($choices[$expiry])) {
            throw new \InvalidArgumentException('Unknown expiry.');
        }

        $token = self::newToken();
        $now = time();
        $connection = Database::connection();
        $connection->beginTransaction();

        try {
            $statement = $connection->prepare(
                'INSERT INTO share_links (case_id, token_hash, label_encrypted, passcode_hash, expires_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([
                $caseId,
                self::hashToken($token),
                $label === null || trim($label) === '' ? null : SealedText::seal(trim($label), self::LABEL_CONTEXT),
                $passcode === null || $passcode === '' ? null : password_hash($passcode, PASSWORD_ARGON2ID),
                gmdate('Y-m-d H:i:s', $now + (int) $choices[$expiry]['seconds']),
                gmdate('Y-m-d H:i:s', $now),
            ]);
            $linkId = (int) $connection->lastInsertId();

            $insert = $connection->prepare('INSERT INTO share_link_files (share_link_id, evidence_file_id) VALUES (?, ?)');
            foreach (array_unique(array_map('intval', $fileIds)) as $fileId) {
                $insert->execute([$linkId, $fileId]);
            }

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return ['token' => $token, 'id' => $linkId];
    }

    public static function newToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** What they copy: the token after "#", never in the path or query string. */
    public static function url(string $token): string
    {
        return rtrim((string) Env::get('APP_URL', ''), '/') . '/share#' . $token;
    }

    /** A link that can still be opened: not expired, not locked by wrong passcodes. */
    public function findUsable(string $token): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_\-]{40,60}$/', $token)) {
            return null;
        }

        $statement = Database::connection()->prepare('SELECT * FROM share_links WHERE token_hash = ? LIMIT 1');
        $statement->execute([self::hashToken($token)]);
        $link = $statement->fetch() ?: null;

        return $link !== null && self::usable($link) ? $link : null;
    }

    public function find(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM share_links WHERE id = ? LIMIT 1');
        $statement->execute([$id]);
        $link = $statement->fetch() ?: null;

        return $link !== null && self::usable($link) ? $link : null;
    }

    public static function usable(array $link): bool
    {
        return (string) $link['expires_at'] > gmdate('Y-m-d H:i:s')
            && (int) $link['failed_attempts'] < self::maxAttempts();
    }

    public static function maxAttempts(): int
    {
        return max(1, (int) Config::get('survivor_reports.share_passcode_max_attempts', 10));
    }

    /** Checks a passcode; a wrong one counts toward locking the link. */
    public function checkPasscode(array $link, string $passcode): bool
    {
        if ($link['passcode_hash'] === null) {
            return true;
        }

        if ($passcode !== '' && password_verify($passcode, (string) $link['passcode_hash'])) {
            return true;
        }

        Database::connection()->prepare('UPDATE share_links SET failed_attempts = failed_attempts + 1 WHERE id = ?')->execute([(int) $link['id']]);

        return false;
    }

    public function markOpened(int $linkId): void
    {
        Database::connection()->prepare('UPDATE share_links SET last_opened_at = ? WHERE id = ?')->execute([gmdate('Y-m-d H:i:s'), $linkId]);
    }

    /** The link's files, quarantined ones left out. */
    public function files(int $linkId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT e.* FROM share_link_files f JOIN evidence_files e ON e.id = f.evidence_file_id
             WHERE f.share_link_id = ? AND e.quarantined_at IS NULL
             ORDER BY e.uploaded_at, e.id'
        );
        $statement->execute([$linkId]);

        return $statement->fetchAll();
    }

    /** Every link they have made, with its label and file count, newest first. */
    public function forCase(int $caseId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT l.*, (SELECT COUNT(*) FROM share_link_files f JOIN evidence_files e ON e.id = f.evidence_file_id
                          WHERE f.share_link_id = l.id AND e.quarantined_at IS NULL) AS file_count
             FROM share_links l WHERE l.case_id = ? ORDER BY l.created_at DESC, l.id DESC'
        );
        $statement->execute([$caseId]);

        return array_map(static function (array $link): array {
            $link['label'] = SealedText::open($link['label_encrypted'], self::LABEL_CONTEXT);
            $link['usable'] = self::usable($link);

            return $link;
        }, $statement->fetchAll());
    }

    public function countForCase(int $caseId): int
    {
        $statement = Database::connection()->prepare('SELECT COUNT(*) FROM share_links WHERE case_id = ?');
        $statement->execute([$caseId]);

        return (int) $statement->fetchColumn();
    }

    /** Revoking deletes the link: it stops working at once and leaves nothing behind. */
    public function revoke(int $caseId, int $linkId): bool
    {
        $statement = Database::connection()->prepare('DELETE FROM share_links WHERE id = ? AND case_id = ?');
        $statement->execute([$linkId, $caseId]);

        return $statement->rowCount() > 0;
    }

    /** Deletes expired links. @return int how many */
    public function deleteExpired(): int
    {
        $statement = Database::connection()->prepare('DELETE FROM share_links WHERE expires_at <= ?');
        $statement->execute([gmdate('Y-m-d H:i:s')]);

        return $statement->rowCount();
    }

    public static function label(array $link): ?string
    {
        return SealedText::open($link['label_encrypted'] ?? null, self::LABEL_CONTEXT);
    }
}
