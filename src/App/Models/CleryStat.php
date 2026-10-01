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

    /**
     * @return list<array> every row for a school, oldest year first. Each row
     *         is the sum of the school's campuses in clery_campus_stats.
     */
    public static function forSchool(int $schoolId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM clery_stats WHERE school_id = ? ORDER BY year ASC, location ASC'
        );
        $statement->execute([$schoolId]);

        return $statement->fetchAll();
    }

    /**
     * Rebuild the school rows for some locations and years from
     * clery_campus_stats: each figure is the sum of the school's campuses, and
     * NULL only when no campus has a figure. Rows that come out the same are
     * left untouched.
     *
     * @param list<string> $locations
     * @param list<int> $years
     * @return int school-year-location rows inserted or changed
     */
    public static function rebuildFromCampuses(array $locations, array $years): int
    {
        $locations = array_values(array_intersect($locations, self::LOCATIONS));
        $years = array_values(array_map('intval', $years));
        if ($locations === [] || $years === []) {
            return 0;
        }

        $columns = implode(', ', self::OFFENSES);
        $sums = implode(', ', array_map(static fn (string $o): string => "SUM({$o})", self::OFFENSES));
        $updates = implode(', ', array_map(static fn (string $o): string => "{$o} = VALUES({$o})", self::OFFENSES));

        $statement = Database::connection()->prepare(
            "INSERT INTO clery_stats (school_id, year, location, {$columns})
             SELECT school_id, year, location, {$sums}
             FROM clery_campus_stats
             WHERE location IN (" . implode(', ', array_fill(0, count($locations), '?')) . ')
               AND year IN (' . implode(', ', array_fill(0, count($years), '?')) . ")
             GROUP BY school_id, year, location
             ON DUPLICATE KEY UPDATE {$updates}"
        );
        $statement->execute([...$locations, ...$years]);

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
