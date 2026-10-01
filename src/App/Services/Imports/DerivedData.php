<?php

namespace Keel\App\Services\Imports;

use Keel\App\Models\CleryStat;
use Keel\Core\Database;

/**
 * Values computed from imported data and stored so public pages do not
 * recompute them on every request. Rebuilt at the end of every import; each
 * rebuild starts from the imported rows, so it can run any number of times.
 */
final class DerivedData
{
    public static function rebuild(): void
    {
        self::rebuildHasCleryData();
    }

    /**
     * schools.has_clery_data: the school has at least one Clery figure. Only
     * those schools are listed in search, state lists and the sitemap.
     */
    public static function rebuildHasCleryData(): void
    {
        $anyFigure = implode(' OR ', array_map(static fn (string $o): string => "c.{$o} IS NOT NULL", CleryStat::OFFENSES));

        Database::connection()->exec(
            "UPDATE schools s
             SET has_clery_data = EXISTS (SELECT 1 FROM clery_stats c WHERE c.school_id = s.id AND ({$anyFigure}))"
        );
    }
}
