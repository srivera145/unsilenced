<?php

namespace Keel\App\Models;

use Keel\Core\Database;

class ResourcePage
{
    public const FILLABLE = ['slug', 'title', 'browser_title', 'summary', 'body', 'sort_order', 'is_published', 'updated_by'];

    public static function find(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM resource_pages WHERE id = ? LIMIT 1');
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }

    public static function findPublishedBySlug(string $slug): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM resource_pages WHERE slug = ? AND is_published = 1 LIMIT 1');
        $statement->execute([$slug]);

        return $statement->fetch() ?: null;
    }

    public static function published(): array
    {
        return Database::connection()->query(
            'SELECT id, slug, title, browser_title, summary, updated_at FROM resource_pages WHERE is_published = 1 ORDER BY sort_order ASC, title ASC'
        )->fetchAll();
    }

    public static function all(): array
    {
        return Database::connection()->query('SELECT * FROM resource_pages ORDER BY sort_order ASC, title ASC')->fetchAll();
    }

    public static function slugTaken(string $slug, ?int $exceptId = null): bool
    {
        $statement = Database::connection()->prepare('SELECT id FROM resource_pages WHERE slug = ? LIMIT 1');
        $statement->execute([$slug]);
        $id = $statement->fetchColumn();

        return $id !== false && (int) $id !== $exceptId;
    }

    public static function create(array $attributes): int
    {
        $attributes = array_intersect_key($attributes, array_flip(self::FILLABLE));
        $columns = array_keys($attributes);

        $statement = Database::connection()->prepare(
            'INSERT INTO resource_pages (' . implode(', ', $columns) . ')
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
        $statement = Database::connection()->prepare("UPDATE resource_pages SET {$assignments} WHERE id = ?");
        $statement->execute([...array_values($attributes), $id]);
    }

    public static function delete(int $id): void
    {
        $statement = Database::connection()->prepare('DELETE FROM resource_pages WHERE id = ?');
        $statement->execute([$id]);
    }
}
