<?php

namespace Keel\App\Models;

use Keel\App\Services\Survivor\SealedText;
use Keel\Core\Database;

/**
 * An evidence file's record. The file itself is encrypted on disk
 * (VaultService); this row holds its fingerprint, its keys (wrapped by the
 * master key) and its name (encrypted).
 */
class EvidenceFile
{
    private const COLUMNS = [
        'kind', 'mime_type', 'size_bytes', 'sha256', 'uploaded_at', 'name_encrypted', 'blob_name', 'blob_key',
        'admin_blob_name', 'admin_blob_key', 'admin_copy', 'orientation',
    ];

    /** @param array $stored what VaultService::store() returned */
    public static function create(int $reportId, array $stored): int
    {
        $values = array_intersect_key($stored, array_flip(self::COLUMNS));
        $values['report_id'] = $reportId;
        $names = array_keys($values);

        $statement = Database::connection()->prepare(
            'INSERT INTO evidence_files (' . implode(', ', $names) . ') VALUES (' . implode(', ', array_fill(0, count($names), '?')) . ')'
        );
        $statement->execute(array_values($values));

        return (int) Database::connection()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM evidence_files WHERE id = ? LIMIT 1');
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }

    /** A file of this report, or null. */
    public static function findForReport(int $reportId, int $id): ?array
    {
        $file = self::find($id);

        return $file !== null && (int) $file['report_id'] === $reportId ? $file : null;
    }

    /** Oldest first. */
    public static function forReport(int $reportId): array
    {
        $statement = Database::connection()->prepare('SELECT * FROM evidence_files WHERE report_id = ? ORDER BY uploaded_at, id');
        $statement->execute([$reportId]);

        return $statement->fetchAll();
    }

    public static function countForReport(int $reportId): int
    {
        $statement = Database::connection()->prepare('SELECT COUNT(*) FROM evidence_files WHERE report_id = ?');
        $statement->execute([$reportId]);

        return (int) $statement->fetchColumn();
    }

    public static function name(array $file): string
    {
        return (string) SealedText::open($file['name_encrypted'] ?? null, 'evidence_files.name');
    }

    public static function isQuarantined(array $file): bool
    {
        return !empty($file['quarantined_at']);
    }

    /** Admins can view it: not quarantined, and the metadata-free copy exists. */
    public static function viewableByAdmin(array $file): bool
    {
        return !self::isQuarantined($file) && !empty($file['admin_blob_name']);
    }

    public static function markReviewed(int $id, ?int $adminId): void
    {
        $statement = Database::connection()->prepare('UPDATE evidence_files SET reviewed_at = ?, reviewed_by = ? WHERE id = ? AND reviewed_at IS NULL');
        $statement->execute([gmdate('Y-m-d H:i:s'), $adminId, $id]);
    }

    public static function quarantine(int $id, ?int $adminId): void
    {
        $statement = Database::connection()->prepare('UPDATE evidence_files SET quarantined_at = ?, quarantined_by = ? WHERE id = ? AND quarantined_at IS NULL');
        $statement->execute([gmdate('Y-m-d H:i:s'), $adminId, $id]);
    }

    public static function delete(int $id): void
    {
        Database::connection()->prepare('DELETE FROM evidence_files WHERE id = ?')->execute([$id]);
    }

    /** The "Evidence on file" badge: at least one file an admin has viewed and not quarantined. */
    public static function hasReviewedFile(int $reportId): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1 FROM evidence_files WHERE report_id = ? AND reviewed_at IS NOT NULL AND quarantined_at IS NULL LIMIT 1'
        );
        $statement->execute([$reportId]);

        return $statement->fetchColumn() !== false;
    }
}
