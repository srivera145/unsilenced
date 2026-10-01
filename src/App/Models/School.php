<?php

namespace Keel\App\Models;

use Keel\App\Support\Format;
use Keel\Core\Database;

class School
{
    public const PER_PAGE = 25;

    /** Columns an admin can write. unitid and slug are validated by the controller. */
    public const FILLABLE = ['unitid', 'name', 'slug', 'city', 'state', 'control', 'enrollment', 'enrollment_year'];

    public static function find(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM schools WHERE id = ? LIMIT 1');
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }

    public static function findByUnitid(int $unitid): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM schools WHERE unitid = ? LIMIT 1');
        $statement->execute([$unitid]);

        return $statement->fetch() ?: null;
    }

    public static function findByStateAndSlug(string $state, string $slug): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM schools WHERE state = ? AND slug = ? LIMIT 1');
        $statement->execute([strtoupper($state), $slug]);

        return $statement->fetch() ?: null;
    }

    /**
     * Name or city search, optionally narrowed to a state. Names that start
     * with the query sort first.
     *
     * @return array{rows: list<array>, total: int}
     */
    public static function search(string $query, ?string $state, int $page, int $perPage = self::PER_PAGE): array
    {
        $where = [];
        $params = [];

        $query = trim($query);
        if ($query !== '') {
            $like = '%' . self::escapeLike($query) . '%';
            $where[] = '(name LIKE ? OR city LIKE ?)';
            $params[] = $like;
            $params[] = $like;
        }

        if ($state !== null && $state !== '') {
            $where[] = 'state = ?';
            $params[] = strtoupper($state);
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

        $count = Database::connection()->prepare("SELECT COUNT(*) FROM schools {$whereSql}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $orderParams = [];
        $order = 'name ASC, city ASC';
        if ($query !== '') {
            $order = '(name LIKE ?) DESC, ' . $order;
            $orderParams[] = self::escapeLike($query) . '%';
        }

        $statement = Database::connection()->prepare(
            "SELECT id, unitid, name, slug, city, state, control, enrollment, enrollment_year
             FROM schools {$whereSql}
             ORDER BY {$order}
             LIMIT ? OFFSET ?"
        );

        $position = 1;
        foreach (array_merge($params, $orderParams) as $value) {
            $statement->bindValue($position++, $value);
        }
        $statement->bindValue($position++, $perPage, \PDO::PARAM_INT);
        $statement->bindValue($position, max(0, ($page - 1) * $perPage), \PDO::PARAM_INT);
        $statement->execute();

        return ['rows' => $statement->fetchAll(), 'total' => $total];
    }

    /** @return array<string, int> state code => number of schools */
    public static function countsByState(): array
    {
        $rows = Database::connection()->query('SELECT state, COUNT(*) AS total FROM schools GROUP BY state')->fetchAll();
        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['state']] = (int) $row['total'];
        }

        return $counts;
    }

    /** Every school's state and slug, for the sitemap. */
    public static function allForSitemap(): array
    {
        return Database::connection()->query('SELECT state, slug, updated_at FROM schools ORDER BY state, slug')->fetchAll();
    }

    public static function count(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM schools')->fetchColumn();
    }

    public static function create(array $attributes): int
    {
        $attributes = array_intersect_key($attributes, array_flip(self::FILLABLE));
        $columns = array_keys($attributes);

        $statement = Database::connection()->prepare(
            'INSERT INTO schools (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')'
        );
        $statement->execute(array_values($attributes));

        return (int) Database::connection()->lastInsertId();
    }

    /** @return int rows changed (0 when nothing differed) */
    public static function update(int $id, array $attributes): int
    {
        $attributes = array_intersect_key($attributes, array_flip(self::FILLABLE));
        if ($attributes === []) {
            return 0;
        }

        $assignments = implode(', ', array_map(static fn (string $column): string => "{$column} = ?", array_keys($attributes)));
        $statement = Database::connection()->prepare("UPDATE schools SET {$assignments} WHERE id = ?");
        $statement->execute([...array_values($attributes), $id]);

        return $statement->rowCount();
    }

    public static function delete(int $id): void
    {
        $statement = Database::connection()->prepare('DELETE FROM schools WHERE id = ?');
        $statement->execute([$id]);
    }

    public static function slugTaken(string $state, string $slug, ?int $exceptId = null): bool
    {
        $statement = Database::connection()->prepare('SELECT id FROM schools WHERE state = ? AND slug = ? LIMIT 1');
        $statement->execute([strtoupper($state), $slug]);
        $id = $statement->fetchColumn();

        return $id !== false && (int) $id !== $exceptId;
    }

    /**
     * A slug that is free in the state: the name, then name + city, then
     * name + UNITID. $taken is a set of slugs already used in the state.
     */
    public static function uniqueSlug(string $name, ?string $city, int $unitid, array $taken): string
    {
        $base = Format::slug($name);
        if (!isset($taken[$base])) {
            return $base;
        }

        if ($city !== null && trim($city) !== '') {
            $withCity = Format::slug($name . ' ' . $city);
            if (!isset($taken[$withCity])) {
                return $withCity;
            }
        }

        return $base . '-' . $unitid;
    }

    public static function path(array $school): string
    {
        return '/schools/' . strtolower((string) $school['state']) . '/' . rawurlencode((string) $school['slug']);
    }

    private static function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }
}
