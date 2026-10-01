<?php

namespace Keel\App\Models;

use Keel\Core\Database;

/**
 * Clery counts for one campus (UNITID_P), calendar year and location, as the
 * newest imported file that has a figure reports them. clery_stats, which the
 * site reads, is the sum of a school's campuses (CleryStat::rebuildFromCampuses).
 */
class CleryCampusStat
{
    /**
     * Insert or update one campus-year-location, writing only the offenses
     * given. Offenses not in $counts keep whatever an earlier import stored, so
     * a crime file and a VAWA file for the same location fill the same row.
     *
     * $vintage is the newest calendar year in the file the counts come from. A
     * stored figure is replaced only by a figure from the same or a newer
     * vintage, and never by a blank: a blank in a newer file means that file
     * has no figure, not that an older file's figure was wrong. The result does
     * not depend on the order files are imported in.
     *
     * @param array<string, int|null> $counts offense => count
     * @return int 1 inserted, 2 updated, 0 unchanged (MySQL affected-rows semantics)
     */
    public static function upsert(int $schoolId, string $campusId, int $year, string $location, array $counts, int $vintage): int
    {
        $counts = array_intersect_key($counts, array_flip(CleryStat::OFFENSES));
        if ($counts === []) {
            throw new \InvalidArgumentException('CleryCampusStat::upsert needs at least one offense.');
        }

        if (!in_array($location, CleryStat::LOCATIONS, true)) {
            throw new \InvalidArgumentException("Unknown Clery location {$location}.");
        }

        $offenses = array_keys($counts);
        $columns = [];
        foreach ($offenses as $offense) {
            $columns[] = $offense;
            $columns[] = "{$offense}_vintage";
        }

        // MySQL applies ON DUPLICATE KEY UPDATE assignments left to right, and
        // a later assignment sees the earlier ones' new values. Every count is
        // therefore assigned before any vintage, so each test below still reads
        // the vintage stored before this import.
        $replaces = static fn (string $o): string => "VALUES({$o}) IS NOT NULL AND ({$o}_vintage IS NULL OR VALUES({$o}_vintage) >= {$o}_vintage)";
        $updates = array_merge(
            array_map(static fn (string $o): string => "{$o} = IF({$replaces($o)}, VALUES({$o}), {$o})", $offenses),
            array_map(static fn (string $o): string => "{$o}_vintage = IF({$replaces($o)}, VALUES({$o}_vintage), {$o}_vintage)", $offenses),
        );

        $statement = Database::connection()->prepare(
            'INSERT INTO clery_campus_stats (school_id, campus_id, year, location, ' . implode(', ', $columns) . ')
             VALUES (?, ?, ?, ?, ' . implode(', ', array_fill(0, count($columns), '?')) . ')
             ON DUPLICATE KEY UPDATE ' . implode(', ', $updates)
        );

        $statement->bindValue(1, $schoolId, \PDO::PARAM_INT);
        $statement->bindValue(2, $campusId);
        $statement->bindValue(3, $year, \PDO::PARAM_INT);
        $statement->bindValue(4, $location);
        $position = 5;
        foreach ($counts as $value) {
            $statement->bindValue($position++, $value, $value === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
            $statement->bindValue($position++, $value === null ? null : $vintage, $value === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        }

        $statement->execute();

        return $statement->rowCount();
    }

    /** @return list<array> every campus row for a school, oldest year first */
    public static function forSchool(int $schoolId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM clery_campus_stats WHERE school_id = ? ORDER BY year ASC, location ASC, campus_id ASC'
        );
        $statement->execute([$schoolId]);

        return $statement->fetchAll();
    }
}
