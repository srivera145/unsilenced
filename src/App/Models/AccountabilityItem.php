<?php

namespace Keel\App\Models;

use Keel\Core\Database;

class AccountabilityItem
{
    public const FILLABLE = [
        'school_id', 'type', 'item_date', 'summary', 'status', 'source_name', 'source_url', 'is_published',
        'name_check_confirmed_by', 'name_check_confirmed_at', 'created_by', 'updated_by',
    ];

    public static function find(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM accountability_items WHERE id = ? LIMIT 1');
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }

    /** Published items for a school's public page, newest first. */
    public static function publishedForSchool(int $schoolId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM accountability_items
             WHERE school_id = ? AND is_published = 1
             ORDER BY item_date DESC, id DESC'
        );
        $statement->execute([$schoolId]);

        return $statement->fetchAll();
    }

    /** Admin list, newest first, optionally for one school. */
    public static function adminList(?int $schoolId, int $limit = 200): array
    {
        $sql = 'SELECT a.*, s.name AS school_name, s.state AS school_state, s.slug AS school_slug
                FROM accountability_items a
                JOIN schools s ON s.id = a.school_id';
        $params = [];

        if ($schoolId !== null) {
            $sql .= ' WHERE a.school_id = ?';
            $params[] = $schoolId;
        }

        $sql .= ' ORDER BY a.item_date DESC, a.id DESC LIMIT ' . max(1, $limit);

        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public static function create(array $attributes): int
    {
        $attributes = array_intersect_key($attributes, array_flip(self::FILLABLE));
        $columns = array_keys($attributes);

        $statement = Database::connection()->prepare(
            'INSERT INTO accountability_items (' . implode(', ', $columns) . ')
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
        $statement = Database::connection()->prepare("UPDATE accountability_items SET {$assignments} WHERE id = ?");
        $statement->execute([...array_values($attributes), $id]);
    }

    public static function delete(int $id): void
    {
        $statement = Database::connection()->prepare('DELETE FROM accountability_items WHERE id = ?');
        $statement->execute([$id]);
    }

    public static function count(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM accountability_items')->fetchColumn();
    }
}
