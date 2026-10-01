<?php

namespace Keel\App\Models;

use Keel\Core\Database;

/**
 * A school's Clery totals for one year and location as one imported file
 * reported them: the sum of the school's campuses in that file. clery_stats is
 * capped at the highest of these (CleryStat::rebuildFromCampuses), so reports a
 * later file moved from a dropped campus to another one are not counted twice.
 */
class CleryFileTotal
{
    /**
     * Store one file's total for a school, year and location, writing only the
     * offenses given (a crime file and a VAWA file fill the same row).
     * Re-importing the same file writes the same values.
     *
     * @param array<string, int|null> $counts offense => total, null when every campus cell was blank
     */
    public static function upsert(int $schoolId, int $year, string $location, int $vintage, array $counts): void
    {
        $counts = array_intersect_key($counts, array_flip(CleryStat::OFFENSES));
        if ($counts === []) {
            throw new \InvalidArgumentException('CleryFileTotal::upsert needs at least one offense.');
        }

        if (!in_array($location, CleryStat::LOCATIONS, true)) {
            throw new \InvalidArgumentException("Unknown Clery location {$location}.");
        }

        $offenses = array_keys($counts);
        $updates = implode(', ', array_map(static fn (string $o): string => "{$o} = VALUES({$o})", $offenses));

        $statement = Database::connection()->prepare(
            'INSERT INTO clery_file_totals (school_id, year, location, vintage, ' . implode(', ', $offenses) . ')
             VALUES (?, ?, ?, ?, ' . implode(', ', array_fill(0, count($offenses), '?')) . ")
             ON DUPLICATE KEY UPDATE {$updates}"
        );

        $statement->bindValue(1, $schoolId, \PDO::PARAM_INT);
        $statement->bindValue(2, $year, \PDO::PARAM_INT);
        $statement->bindValue(3, $location);
        $statement->bindValue(4, $vintage, \PDO::PARAM_INT);
        $position = 5;
        foreach ($counts as $value) {
            $statement->bindValue($position++, $value, $value === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        }

        $statement->execute();
    }
}
