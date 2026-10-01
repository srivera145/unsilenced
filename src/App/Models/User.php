<?php

namespace Keel\App\Models;

use Keel\Core\Database;

class User
{
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public static function findByStripeCustomerId(string $stripeCustomerId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE stripe_customer_id = ? LIMIT 1');
        $stmt->execute([$stripeCustomerId]);
        return $stmt->fetch() ?: null;
    }

    public static function forOrganization(int $organizationId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE organization_id = ? ORDER BY created_at ASC, id ASC');
        $stmt->execute([$organizationId]);
        return $stmt->fetchAll();
    }

    public static function updateThemePreference(int $id, string $theme): void
    {
        $stmt = Database::connection()->prepare('UPDATE users SET theme_preference = ? WHERE id = ?');
        $stmt->execute([$theme, $id]);
    }

    public static function isAdmin(?array $user): bool
    {
        return $user !== null && (int) ($user['is_admin'] ?? 0) === 1;
    }

    /** Creates the user if needed. Returns the user's id. */
    public static function setAdmin(string $email, bool $isAdmin): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO users (email, is_admin, created_at) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE is_admin = VALUES(is_admin)'
        );
        $stmt->execute([$email, $isAdmin ? 1 : 0]);

        return (int) (self::findByEmail($email)['id'] ?? 0);
    }
}
