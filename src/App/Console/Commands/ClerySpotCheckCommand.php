<?php

namespace Keel\App\Console\Commands;

use Keel\App\Models\CleryCampusStat;
use Keel\App\Models\CleryStat;
use Keel\App\Models\School;
use Keel\App\Services\Imports\CleryImporter;
use Keel\App\Services\SchoolProfileService;
use Keel\App\Support\Config;
use Keel\App\Support\CsvFile;
use Keel\App\Support\Format;
use Keel\Core\Database;

/**
 * clery:spot-check — print what we store for a school next to the raw rows in
 * the Clery files we imported, as Markdown, for checking by hand against the
 * Campus Safety website (https://ope.ed.gov/campussafety/).
 *
 *   php database/console.php clery:spot-check 190415 204796
 *
 * For every year and location: the school figure we store (the sum of its
 * campuses), then for each campus the figure we store and which file it came
 * from, followed by that campus's raw cells in every file that covers the
 * year. Also flags any year where on-campus student housing reports more than
 * on campus, which should not happen because housing is part of on campus.
 */
class ClerySpotCheckCommand extends Command
{
    private const SHOWN = ['rape', 'fondling', 'dating_violence', 'domestic_violence', 'stalking'];

    public static function usage(): string
    {
        return 'clery:spot-check <unitid> [unitid ...]';
    }

    public function handle(array $arguments): int
    {
        [$positional] = $this->parse($arguments);
        $unitids = array_values(array_filter(array_map('intval', $positional)));

        if ($unitids === []) {
            return $this->fail('Usage: php database/console.php ' . self::usage());
        }

        $raw = $this->rawRows($unitids);
        $labels = (array) Config::get('locations', []);
        $offenseLabels = (array) Config::get('offenses', []);

        foreach ($unitids as $unitid) {
            $school = School::findByUnitid($unitid);
            if ($school === null) {
                $this->line("## UNITID {$unitid}: not in the schools table");
                $this->line('');
                continue;
            }

            $stored = [];
            foreach (CleryStat::forSchool((int) $school['id']) as $row) {
                $stored[(int) $row['year']][(string) $row['location']] = $row;
            }
            $campusStored = [];
            foreach (CleryCampusStat::forSchool((int) $school['id']) as $row) {
                $campusStored[(int) $row['year']][(string) $row['location']][(string) $row['campus_id']] = $row;
            }
            $profile = (new SchoolProfileService())->build($school);

            $this->line('## ' . $school['name'] . " (UNITID {$unitid})");
            $this->line('');
            $this->line(sprintf(
                '%s, %s · %s · %s · page: %s',
                $school['city'] ?? '',
                $school['state'],
                Config::get('controls.' . ($school['control'] ?? ''), 'type not reported'),
                $school['enrollment'] !== null ? Format::number((int) $school['enrollment']) . ' students (IPEDS ' . $school['enrollment_year'] . ')' : 'no enrollment figure',
                School::path($school)
            ));

            $campuses = [];
            foreach ($raw[$unitid] ?? [] as $byYear) {
                foreach ($byYear as $byVintage) {
                    foreach ($byVintage as $campusRows) {
                        foreach ($campusRows as $campusId => $campus) {
                            $campuses[$campusId] = $campus['name'];
                        }
                    }
                }
            }
            ksort($campuses);
            $this->line('');
            $this->line('Campus rows in the files (UNITID_P, BRANCH): ' . ($campuses === [] ? 'none' : implode('; ', array_map(
                static fn ($id, $name): string => "{$id} {$name}",
                array_keys($campuses),
                $campuses
            ))) . '.');
            $this->line('');

            $this->line('| Year | Location | Row | ' . implode(' | ', array_map(static fn (string $o): string => $offenseLabels[$o] ?? $o, self::SHOWN)) . ' |');
            $this->line('|---|---|---|' . str_repeat('--:|', count(self::SHOWN)));

            $years = array_keys($stored);
            rsort($years);
            $flags = [];

            foreach ($years as $year) {
                foreach (CleryStat::LOCATIONS as $location) {
                    $row = $stored[$year][$location] ?? null;
                    $this->line(sprintf(
                        '| %d | %s | **Stored: sum of campuses** | %s |',
                        $year,
                        $labels[$location] ?? $location,
                        implode(' | ', array_map(static fn (string $o): string => '**' . self::cell($row, $o) . '**', self::SHOWN))
                    ));

                    $byVintage = $raw[$unitid][$location][$year] ?? [];
                    krsort($byVintage);
                    $campusIds = array_keys($campusStored[$year][$location] ?? []);
                    foreach ($byVintage as $campusRows) {
                        $campusIds = array_merge($campusIds, array_map('strval', array_keys($campusRows)));
                    }
                    $campusIds = array_unique($campusIds);
                    sort($campusIds);

                    foreach ($campusIds as $campusId) {
                        $campusRow = $campusStored[$year][$location][$campusId] ?? null;
                        $this->line(sprintf(
                            '|  |  | %s stored%s | %s |',
                            $campusId,
                            self::fromLabel($campusRow),
                            implode(' | ', array_map(static fn (string $o): string => self::cell($campusRow, $o), self::SHOWN))
                        ));

                        foreach ($byVintage as $campusRows) {
                            if (!isset($campusRows[$campusId])) {
                                continue;
                            }
                            $campus = $campusRows[$campusId];
                            $this->line(sprintf(
                                '|  |  | ↳ raw, %s | %s |',
                                $campus['files'],
                                implode(' | ', array_map(static fn (string $o): string => $campus['values'][$o] ?? 'n/a', self::SHOWN))
                            ));
                        }
                    }
                }

                $totals = $profile['by_year'][$year]['totals'] ?? [];
                $this->line(sprintf(
                    '| %d | **Total on the page** (on campus + noncampus + public property) |  | %s |',
                    $year,
                    implode(' | ', array_map(static fn (string $o): string => '**' . (($totals[$o] ?? null) === null ? 'blank' : Format::number($totals[$o])) . '**', self::SHOWN))
                ));

                foreach (CleryStat::OFFENSES as $offense) {
                    $housing = $stored[$year]['on_campus_housing'][$offense] ?? null;
                    $campus = $stored[$year]['on_campus'][$offense] ?? null;
                    if ($housing !== null && $campus !== null && (int) $housing > (int) $campus) {
                        $flags[] = "{$year} " . ($offenseLabels[$offense] ?? $offense) . ": student housing {$housing} > on campus {$campus}";
                    }
                }
            }

            $this->line('');
            $this->line($flags === []
                ? 'Student housing never exceeds on campus for this school.'
                : 'FLAG, student housing exceeds on campus: ' . implode('; ', $flags) . '. The page total does not add housing, so nothing is double-counted.');
            $this->line('');
        }

        return 0;
    }

    private static function cell(?array $row, string $offense): string
    {
        return $row === null || $row[$offense] === null ? 'blank' : (string) (int) $row[$offense];
    }

    /** " (from the 2022–24 file)" for a campus row, from its offenses' vintages. */
    private static function fromLabel(?array $row): string
    {
        if ($row === null) {
            return ' (no row)';
        }

        $vintages = array_values(array_unique(array_filter(array_map(
            static fn (string $o) => $row["{$o}_vintage"] ?? null,
            self::SHOWN
        ))));
        sort($vintages);

        if ($vintages === []) {
            return ' (no figure in any file)';
        }

        return ' (from the ' . implode(' and ', array_map(
            static fn ($v): string => ((int) $v - 2) . '–' . substr((string) $v, -2),
            $vintages
        )) . (count($vintages) > 1 ? ' files)' : ' file)');
    }

    /**
     * Raw cells for the requested institutions from every Clery file a
     * completed import run read.
     *
     * @param list<int> $unitids
     * @return array<int, array<string, array<int, array<int, array<string, array{name: string, files: string, values: array<string, string>}>>>>
     *   unitid => location => year => vintage => campus id => row
     */
    private function rawRows(array $unitids): array
    {
        $wanted = array_flip($unitids);
        $digits = (int) Config::get('clery.unitid_digits', 6);
        $unitidColumn = (string) Config::get('clery.unitid_column', 'UNITID_P');
        $raw = [];

        $runs = Database::connection()->query(
            "SELECT file_path, MAX(location) AS location FROM import_runs
             WHERE kind = 'clery' AND status = 'complete' GROUP BY file_path ORDER BY file_path"
        )->fetchAll();

        foreach ($runs as $run) {
            $path = (string) $run['file_path'];
            if (!is_file($path) || $run['location'] === null) {
                continue;
            }

            $csv = new CsvFile($path);
            $columns = CleryImporter::offenseColumnsByYear($csv->headers());
            if ($columns === []) {
                continue;
            }

            $vintage = max(array_keys($columns));
            $fileLabel = preg_replace('/(crime|vawa)(\d+)\.csv$/i', '*$2', basename($path));

            foreach ($csv->rows() as $row) {
                $campusId = CsvFile::value($row, $unitidColumn);
                $unitid = (int) substr($campusId, 0, $digits);
                if (!isset($wanted[$unitid])) {
                    continue;
                }

                foreach ($columns as $year => $headers) {
                    $entry = &$raw[$unitid][(string) $run['location']][$year][$vintage][$campusId];
                    $entry['name'] ??= CsvFile::value($row, 'BRANCH');
                    $entry['files'] = $fileLabel;
                    foreach ($headers as $offense => $header) {
                        $value = CsvFile::value($row, $header);
                        $entry['values'][$offense] = $value === '' ? 'blank' : $value;
                    }
                    unset($entry);
                }
            }
        }

        return $raw;
    }
}
