<?php

namespace Keel\App\Models;

use Keel\Core\Database;

class CleryStat
{
    public const LOCATIONS = ['on_campus', 'on_campus_housing', 'noncampus', 'public_property'];

    /**
     * Locations whose counts add up to a school's total. on_campus_housing is a
     * subset of on_campus, so adding it would count those reports twice.
     */
    public const TOTAL_LOCATIONS = ['on_campus', 'noncampus', 'public_property'];

    public const OFFENSES = ['rape', 'fondling', 'incest', 'statutory_rape', 'dating_violence', 'domestic_violence', 'stalking'];

    /** @return list<array> every row for a school, oldest year first */
    public static function forSchool(int $schoolId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM clery_stats WHERE school_id = ? ORDER BY year ASC, location ASC'
        );
        $statement->execute([$schoolId]);

        return $statement->fetchAll();
    }

    /**
     * Insert or update one school-year-location, writing only the offenses
     * given. Offenses not in $counts keep whatever an earlier import stored, so
     * a crime file and a VAWA file for the same location fill the same row.
     *
     * @param array<string, int|null> $counts offense => count
     * @return int 1 inserted, 2 updated, 0 unchanged (MySQL affected-rows semantics)
     */
    public static function upsert(int $schoolId, int $year, string $location, array $counts): int
    {
        $counts = array_intersect_key($counts, array_flip(self::OFFENSES));
        if ($counts === []) {
            throw new \InvalidArgumentException('CleryStat::upsert needs at least one offense.');
        }

        if (!in_array($location, self::LOCATIONS, true)) {
            throw new \InvalidArgumentException("Unknown Clery location {$location}.");
        }

        $columns = array_keys($counts);
        $updates = implode(', ', array_map(static fn (string $column): string => "{$column} = VALUES({$column})", $columns));

        $statement = Database::connection()->prepare(
            'INSERT INTO clery_stats (school_id, year, location, ' . implode(', ', $columns) . ')
             VALUES (?, ?, ?, ' . implode(', ', array_fill(0, count($columns), '?')) . ')
             ON DUPLICATE KEY UPDATE ' . $updates
        );

        $statement->bindValue(1, $schoolId, \PDO::PARAM_INT);
        $statement->bindValue(2, $year, \PDO::PARAM_INT);
        $statement->bindValue(3, $location);
        $position = 4;
        foreach ($counts as $value) {
            $statement->bindValue($position++, $value, $value === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        }

        $statement->execute();

        return $statement->rowCount();
    }

    /**
     * Pooled totals for a set of schools in one year, for the state and peer
     * comparisons: every reported offense in the group divided by the group's
     * combined enrollment. Schools without Clery rows for the year, or without
     * an enrollment figure, are left out of both the numerator and the
     * denominator.
     *
     * @param array<string, list<string>> $groups group key => offense columns
     * @param array{state?: string, min?: int, max?: int|null} $filter
     * @return array<string, array{schools: int, enrollment: int, total: int}>
     */
    public static function pooledTotals(int $year, array $groups, array $filter): array
    {
        $selectInner = [];
        $selectOuter = [];

        foreach ($groups as $key => $offenses) {
            $offenses = array_values(array_intersect($offenses, self::OFFENSES));
            $sum = implode(' + ', array_map(static fn (string $o): string => "COALESCE({$o}, 0)", $offenses));
            $has = implode(' OR ', array_map(static fn (string $o): string => "{$o} IS NOT NULL", $offenses));

            $selectInner[] = "SUM({$sum}) AS total_{$key}";
            $selectInner[] = "MAX({$has}) AS has_{$key}";

            $selectOuter[] = "SUM(CASE WHEN t.has_{$key} = 1 THEN 1 ELSE 0 END) AS schools_{$key}";
            $selectOuter[] = "SUM(CASE WHEN t.has_{$key} = 1 THEN s.enrollment ELSE 0 END) AS enrollment_{$key}";
            $selectOuter[] = "SUM(CASE WHEN t.has_{$key} = 1 THEN t.total_{$key} ELSE 0 END) AS total_{$key}";
        }

        $where = ['s.enrollment > 0'];
        $params = [$year];

        if (isset($filter['state'])) {
            $where[] = 's.state = ?';
            $params[] = strtoupper($filter['state']);
        }

        if (isset($filter['min'])) {
            $where[] = 's.enrollment >= ?';
            $params[] = (int) $filter['min'];
        }

        if (isset($filter['max']) && $filter['max'] !== null) {
            $where[] = 's.enrollment <= ?';
            $params[] = (int) $filter['max'];
        }

        $locations = "'" . implode("', '", self::TOTAL_LOCATIONS) . "'";

        $statement = Database::connection()->prepare(
            'SELECT ' . implode(', ', $selectOuter) . '
             FROM schools s
             JOIN (
                SELECT school_id, ' . implode(', ', $selectInner) . "
                FROM clery_stats
                WHERE year = ? AND location IN ({$locations})
                GROUP BY school_id
             ) t ON t.school_id = s.id
             WHERE " . implode(' AND ', $where)
        );
        $statement->execute($params);
        $row = $statement->fetch() ?: [];

        $result = [];
        foreach (array_keys($groups) as $key) {
            $result[$key] = [
                'schools' => (int) ($row["schools_{$key}"] ?? 0),
                'enrollment' => (int) ($row["enrollment_{$key}"] ?? 0),
                'total' => (int) ($row["total_{$key}"] ?? 0),
            ];
        }

        return $result;
    }

    public static function count(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM clery_stats')->fetchColumn();
    }

    /** @return list<int> */
    public static function years(): array
    {
        return array_map('intval', Database::connection()->query('SELECT DISTINCT year FROM clery_stats ORDER BY year')->fetchAll(\PDO::FETCH_COLUMN));
    }
}
