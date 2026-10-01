<?php

namespace Keel\App\Services\Imports;

use Keel\App\Models\CleryCampusStat;
use Keel\App\Models\CleryFileTotal;
use Keel\App\Models\CleryStat;
use Keel\App\Support\Config;
use Keel\App\Support\CsvFile;
use Keel\App\Support\Format;
use Keel\Core\Database;

/**
 * Imports Campus Safety and Security (Clery) counts from one published file.
 *
 * Each published file covers three calendar years: Oncampuscrime222324.csv
 * has RAPE22, RAPE23 and RAPE24. Every year the file has a column for is
 * imported, unless a single year is asked for.
 *
 * The files have one row per campus (UNITID_P = UNITID + campus number), and
 * figures are stored per campus, year and location in clery_campus_stats. The
 * newest year in the file is its vintage, and a campus's figure is only
 * replaced by one from a file of the same or a newer vintage, so the
 * overlapping files can be imported in any order and the newest file's figure
 * wins. A blank in a newer file does not erase a figure an older file
 * reported: blank means the newer file has no figure, not that the figure was
 * zero. A campus that closed and dropped out of later files keeps the figures
 * earlier files reported for it.
 *
 * Each school's total in this file (the sum of its campuses, per year and
 * location) is stored too, in clery_file_totals. The school rows the site
 * reads (clery_stats, one per UNITID + year + location) are then rebuilt as
 * the sum of each school's campuses, capped at the highest total any one file
 * reported: a dropped campus whose reports a later file moved to another
 * campus is not counted twice. All writes happen in one transaction after the
 * file has been read, so a re-run changes nothing.
 *
 * A file only has to contain some of the offenses: the crime files carry the
 * sex offenses and the VAWA files carry dating violence, domestic violence and
 * stalking. Each import writes only the offenses its file contains, so the
 * crime and VAWA files for one location fill the same rows.
 */
class CleryImporter
{
    /**
     * @return array{
     *   unitid: string,
     *   years: list<int>,
     *   vintage: int,
     *   columns: array<int, array<string, string>>,
     *   offenses: list<string>,
     *   missing_offenses: list<string>,
     *   location: ?string,
     *   location_column: ?string
     * }
     * @throws ImportException
     */
    public function resolve(CsvFile $csv, ?int $year, ?string $locationOption, string $fileName): array
    {
        if ($year !== null) {
            SchoolImporter::assertYear($year);
        }

        $unitidColumn = (string) Config::get('clery.unitid_column', 'UNITID_P');
        if (!$csv->has($unitidColumn)) {
            throw new ImportException(implode("\n", [
                "The file is missing the required UNITID column \"{$unitidColumn}\" (config clery.unitid_column).",
                'Columns in the file: ' . SchoolImporter::preview($csv->headers()),
                'Fix the column name in config/unsilenced.php, or check this is a Clery data file.',
            ]));
        }

        $available = self::offenseColumnsByYear($csv->headers());

        if ($available === [] || ($year !== null && !isset($available[$year]))) {
            $patterns = array_values((array) Config::get('clery.offense_columns', []));
            $expected = $year === null
                ? implode(', ', $patterns)
                : implode(', ', array_map(static fn (string $pattern): string => self::headerForYear($pattern, $year), $patterns));

            throw new ImportException(implode("\n", [
                ($year === null ? 'The file has no offense columns for any year.' : "The file has no offense columns for {$year}.") . " Expected at least one of: {$expected}.",
                self::yearsHint($available),
                'Columns in the file: ' . SchoolImporter::preview($csv->headers()),
                'Fix the patterns in config/unsilenced.php (clery.offense_columns), or pass a year the file actually covers.',
            ]));
        }

        $years = $year !== null ? [$year] : array_keys($available);
        $columns = array_intersect_key($available, array_flip($years));

        $offenses = [];
        foreach ($columns as $byOffense) {
            $offenses += array_flip(array_keys($byOffense));
        }

        $configured = array_keys((array) Config::get('clery.offense_columns', []));

        return [
            'unitid' => $unitidColumn,
            'years' => $years,
            'vintage' => max(array_keys($available)),
            'columns' => $columns,
            'offenses' => array_values(array_intersect($configured, array_keys($offenses))),
            'missing_offenses' => array_values(array_diff($configured, array_keys($offenses))),
        ] + $this->resolveLocation($csv, $locationOption, $fileName);
    }

    /** @param ?int $year one year to import, or null for every year the file covers */
    public function import(string $path, ?int $year, ?string $locationOption): array
    {
        $csv = new CsvFile($path);
        $resolved = $this->resolve($csv, $year, $locationOption, basename($path));
        $vintage = $resolved['vintage'];

        $digits = (int) Config::get('clery.unitid_digits', 6);
        $locationValues = array_change_key_case((array) Config::get('clery.location_values', []), CASE_LOWER);

        $result = SchoolImporter::emptyResult();
        $result['columns_found'] = [
            'unitid' => $resolved['unitid'],
            'years' => $resolved['years'],
            'vintage' => $vintage,
            'offenses' => self::headersByOffense($resolved['columns']),
            'missing_offenses' => $resolved['missing_offenses'],
            'location' => $resolved['location'] ?? ('column ' . $resolved['location_column']),
        ];

        $schoolIds = [];
        foreach (Database::connection()->query('SELECT id, unitid FROM schools') as $row) {
            $schoolIds[(int) $row['unitid']] = (int) $row['id'];
        }

        // campus id|year|location => offense => count (null until a number is seen)
        $totals = [];
        $campusSchool = [];
        $locationsSeen = [];
        $unknownUnitids = [];

        foreach ($csv->rows() as $line => $row) {
            $result['rows_read']++;

            $rawUnitid = CsvFile::value($row, $resolved['unitid']);
            $unitid = SchoolImporter::parseUnitid($rawUnitid, $digits);
            if ($unitid === null) {
                SchoolImporter::skip($result, $line, "missing or invalid UNITID \"{$rawUnitid}\"");
                continue;
            }

            if (!isset($schoolIds[$unitid])) {
                $unknownUnitids[$unitid] = true;
                SchoolImporter::skip($result, $line, "UNITID {$unitid}: no school with this UNITID. Import the IPEDS directory file first, or this institution is not in it.");
                continue;
            }

            $location = $resolved['location'];
            if ($location === null) {
                $value = strtolower(CsvFile::value($row, (string) $resolved['location_column']));
                $location = $locationValues[$value] ?? null;
                if ($location === null) {
                    SchoolImporter::skip($result, $line, "UNITID {$unitid}: unknown location \"{$value}\" (config clery.location_values)");
                    continue;
                }
            }

            $campusId = trim($rawUnitid);
            $campusSchool[$campusId] = $schoolIds[$unitid];
            $locationsSeen[$location] = true;

            foreach ($resolved['columns'] as $columnYear => $headers) {
                // A campus listed twice in one file is added up, like campuses are.
                $key = $campusId . '|' . $columnYear . '|' . $location;
                $totals[$key] ??= array_fill_keys(array_keys($headers), null);

                foreach ($headers as $offense => $header) {
                    $raw = CsvFile::value($row, $header);

                    if ($raw === '' || $raw === '.') {
                        continue;
                    }

                    if (!ctype_digit($raw)) {
                        $result['error_count']++;
                        if (count($result['errors']) < \Keel\App\Models\ImportRun::MAX_STORED_ERRORS) {
                            $result['errors'][] = "Line {$line}: UNITID {$unitid} {$header} is \"{$raw}\", not a count; left blank";
                        }
                        continue;
                    }

                    $totals[$key][$offense] = ($totals[$key][$offense] ?? 0) + (int) $raw;
                }
            }
        }

        // school id|year|location => offense => this file's school total (null
        // while every campus cell is blank)
        $fileTotals = [];
        foreach ($totals as $key => $counts) {
            [$campusId, $rowYear, $location] = explode('|', $key, 3);
            $schoolKey = $campusSchool[$campusId] . '|' . $rowYear . '|' . $location;
            $fileTotals[$schoolKey] ??= array_fill_keys(array_keys($counts), null);

            foreach ($counts as $offense => $count) {
                if ($count !== null) {
                    $fileTotals[$schoolKey][$offense] = ($fileTotals[$schoolKey][$offense] ?? 0) + $count;
                }
            }
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            foreach ($totals as $key => $counts) {
                [$campusId, $rowYear, $location] = explode('|', $key, 3);

                match (CleryCampusStat::upsert($campusSchool[$campusId], $campusId, (int) $rowYear, $location, $counts, $vintage)) {
                    1 => $result['rows_added']++,
                    0 => $result['rows_unchanged']++,
                    default => $result['rows_updated']++,
                };
            }

            foreach ($fileTotals as $key => $counts) {
                [$schoolId, $rowYear, $location] = explode('|', $key, 3);
                CleryFileTotal::upsert((int) $schoolId, (int) $rowYear, $location, $vintage, $counts);
            }

            CleryStat::rebuildFromCampuses(array_keys($locationsSeen), $resolved['years']);

            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }

        DerivedData::rebuild();

        $result['message'] = sprintf(
            'Read %s covering %s (newest year %d): %s added, %d updated, %d unchanged; %s skipped (%s). %s for %s; their school totals rebuilt.',
            Format::plural($result['rows_read'], 'row'),
            Format::list(array_map('strval', $resolved['years'])),
            $vintage,
            Format::plural($result['rows_added'], 'campus-year-location row'),
            $result['rows_updated'],
            $result['rows_unchanged'],
            Format::plural($result['rows_skipped'], 'row'),
            Format::plural(count($unknownUnitids), 'unknown UNITID'),
            Format::plural(count($campusSchool), 'campus', 'campuses'),
            Format::plural(count(array_unique($campusSchool)), 'school')
        );

        return $result;
    }

    public static function headerForYear(string $pattern, int $year): string
    {
        return strtr($pattern, [
            '{yyyy}' => (string) $year,
            '{yy}' => substr((string) $year, -2),
        ]);
    }

    /**
     * Which offense columns the file has, by calendar year. Two-digit years
     * are 2000-2089 or 1990-1999 (Clery data starts in the 1990s).
     *
     * @param list<string> $headers
     * @return array<int, array<string, string>> year => offense => header, oldest year first
     */
    public static function offenseColumnsByYear(array $headers): array
    {
        $found = [];

        foreach ((array) Config::get('clery.offense_columns', []) as $offense => $pattern) {
            $regex = '/^' . strtr(preg_quote((string) $pattern, '/'), ['\{yy\}' => '(\d{2})', '\{yyyy\}' => '(\d{4})']) . '$/i';

            foreach ($headers as $header) {
                if (!preg_match($regex, $header, $m)) {
                    continue;
                }

                $year = (int) $m[1];
                if (strlen($m[1]) === 2) {
                    $year += $year >= 90 ? 1900 : 2000;
                }

                $found[$year][(string) $offense] ??= $header;
            }
        }

        ksort($found);

        return $found;
    }

    /**
     * @param array<int, array<string, string>> $columns
     * @return array<string, string> offense => "RAPE22, RAPE23, RAPE24"
     */
    private static function headersByOffense(array $columns): array
    {
        $byOffense = [];
        foreach ($columns as $headers) {
            foreach ($headers as $offense => $header) {
                $byOffense[$offense][] = $header;
            }
        }

        return array_map(static fn (array $headers): string => implode(', ', $headers), $byOffense);
    }

    /** @return array{location: ?string, location_column: ?string} */
    private function resolveLocation(CsvFile $csv, ?string $option, string $fileName): array
    {
        $labels = (array) Config::get('locations', []);

        if ($option !== null && $option !== '') {
            if (!isset($labels[$option])) {
                throw new ImportException('--location must be one of: ' . implode(', ', array_keys($labels)) . ". Got \"{$option}\".");
            }

            return ['location' => $option, 'location_column' => null];
        }

        $column = Config::get('clery.location_column');
        if (is_string($column) && $column !== '' && $csv->has($column)) {
            return ['location' => null, 'location_column' => $column];
        }

        foreach ((array) Config::get('clery.location_filename_patterns', []) as $pattern => $location) {
            if (preg_match((string) $pattern, $fileName)) {
                return ['location' => (string) $location, 'location_column' => null];
            }
        }

        throw new ImportException(implode("\n", [
            "Could not tell which Clery location \"{$fileName}\" covers.",
            'import:clery reads the crime and VAWA files for the four Clery locations, named like Oncampuscrime222324.csv,'
            . ' Residencehallvawa222324.csv, Noncampuscrime222324.csv and Publicpropertyvawa222324.csv. The hate-crime, arrest,'
            . ' discipline, fire, unfounded and "Reported" files are not imported.',
            'For a crime or VAWA file with another name, pass --location=' . implode('|', array_keys($labels))
            . ', or add a pattern to clery.location_filename_patterns in config/unsilenced.php.',
        ]));
    }

    /** @param array<int, array<string, string>> $available */
    private static function yearsHint(array $available): string
    {
        if ($available === []) {
            return 'No column in the file matches any offense pattern for any year.';
        }

        $suffixes = array_map(static fn (int $year): string => substr((string) $year, -2), array_keys($available));

        return 'The file has offense columns for year suffix(es): ' . implode(', ', $suffixes) . '.';
    }
}
