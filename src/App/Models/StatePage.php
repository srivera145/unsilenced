<?php

namespace Keel\App\Models;

use Keel\Core\Database;

class StatePage
{
    public const STATUS_NEEDS_LEGAL_REVIEW = 'needs_legal_review';
    public const STATUS_PUBLISHED = 'published';
    public const STATUSES = [
        self::STATUS_NEEDS_LEGAL_REVIEW => 'Needs legal review',
        self::STATUS_PUBLISHED => 'Published',
    ];

    public const FILLABLE = [
        'code', 'name', 'status', 'statute_of_limitations', 'resources',
        'legal_reviewed_by', 'legal_reviewed_on', 'updated_by',
    ];

    public static function find(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM state_pages WHERE id = ? LIMIT 1');
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }

    public static function findByCode(string $code): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM state_pages WHERE code = ? LIMIT 1');
        $statement->execute([strtoupper($code)]);

        return $statement->fetch() ?: null;
    }

    public static function all(): array
    {
        return Database::connection()->query('SELECT * FROM state_pages ORDER BY name ASC')->fetchAll();
    }

    public static function isPublished(array $page): bool
    {
        return ($page['status'] ?? '') === self::STATUS_PUBLISHED;
    }

    public static function codeTaken(string $code, ?int $exceptId = null): bool
    {
        $statement = Database::connection()->prepare('SELECT id FROM state_pages WHERE code = ? LIMIT 1');
        $statement->execute([strtoupper($code)]);
        $id = $statement->fetchColumn();

        return $id !== false && (int) $id !== $exceptId;
    }

    public static function create(array $attributes): int
    {
        $attributes = array_intersect_key($attributes, array_flip(self::FILLABLE));
        $columns = array_keys($attributes);

        $statement = Database::connection()->prepare(
            'INSERT INTO state_pages (' . implode(', ', $columns) . ')
             VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')'
        );
        $statement->execute(array_values($attributes));

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $attributes): void
    {
        $attributes = array_intersect_key($attributes, array_flip(self::FILLABLE));
        if ($attributes === []) {
            return;
        }

        $assignments = implode(', ', array_map(static fn (string $column): string => "{$column} = ?", array_keys($attributes)));
        $statement = Database::connection()->prepare("UPDATE state_pages SET {$assignments} WHERE id = ?");
        $statement->execute([...array_values($attributes), $id]);
    }

    public static function delete(int $id): void
    {
        $statement = Database::connection()->prepare('DELETE FROM state_pages WHERE id = ?');
        $statement->execute([$id]);
    }
}
