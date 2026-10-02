<?php

namespace Keel\App\Models;

use Keel\App\Services\Survivor\SealedText;
use Keel\Core\Database;

/**
 * A survivor's anonymous "account": their case key's lookup id and Argon2id
 * hash, and an optional email for status updates, encrypted. No name, no
 * password, nothing else. See CaseKeyService.
 */
class SurvivorCase
{
    private const EMAIL_CONTEXT = 'survivor_cases.email';

    public static function create(string $lookupId, string $keyHash, ?string $email): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO survivor_cases (lookup_id, key_hash, email_encrypted, created_at) VALUES (?, ?, ?, ?)'
        );
        $statement->execute([$lookupId, $keyHash, SealedText::seal($email, self::EMAIL_CONTEXT), gmdate('Y-m-d H:i:s')]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM survivor_cases WHERE id = ? LIMIT 1');
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }

    public static function email(array $case): ?string
    {
        return SealedText::open($case['email_encrypted'] ?? null, self::EMAIL_CONTEXT);
    }

    public static function setEmail(int $id, ?string $email): void
    {
        $statement = Database::connection()->prepare('UPDATE survivor_cases SET email_encrypted = ? WHERE id = ?');
        $statement->execute([SealedText::seal($email, self::EMAIL_CONTEXT), $id]);
    }

    /** Called when a report is approved, rejected or withdrawn: the address is no longer needed. */
    public static function forgetEmail(int $id): void
    {
        self::setEmail($id, null);
    }
}
