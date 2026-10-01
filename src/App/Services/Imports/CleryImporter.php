<?php

namespace Keel\App\Services\Imports;

use Keel\App\Models\CleryStat;
use Keel\App\Support\Config;
use Keel\App\Support\CsvFile;
use Keel\App\Support\Format;
use Keel\Core\Database;

/**
 * Imports one year of Campus Safety and Security (Clery) counts from one file.
 *
 * Rows are keyed on UNITID + year + location type. The published files have one
 * row per campus (UNITID_P = UNITID + campus number), so campus rows for the
 * same institution are summed before anything is written. All writes happen in
 * one transaction after the file has been read, so a re-run produces the same
 * totals and never duplicates a row.
 *
 * A file only has to contain some of the offenses: whichever offense columns
 * are present for the year are written, and the others keep what earlier
 * imports stored. That is how a crime file and a VAWA file for the same
 * location and year end up in one row.
 */
class CleryImporter
{
    /**
     * @return array{unitid: string, offenses: array<string, string>, missing_offenses: list<string>, location: ?string, location_column: ?string}
     * @throws ImportException
     */
    public function resolve(CsvFile $csv, int $year, ?string $locationOption, string $fileName): array
    {
        SchoolImporter::assertYear($year);

        $unitidColumn = (string) Config::get('clery.unitid_column', 'UNITID_P');
        if (!$csv->has($unitidColumn)) {
            throw new ImportException(implode("\n", [
                "The file is missing the required UNITID column \"{$unitidColumn}\" (config clery.unitid_column).",
                'Columns in the file: ' . SchoolImporter::preview($csv->headers()),
                'Fix the column name in config/unsilenced.php, or check this is a Clery data file.',
            ]));
        }

        $offenses = [];
        $missing = [];
        foreach ((array) Config::get('clery.offense_columns', []) as $offense => $pattern) {
            $header = self::headerForYear((string) $pattern, $year);
            if ($csv->has($header)) {
                $offenses[$offense] = $header;
            } else {
                $missing[] = $offense;
            }
        }

        if ($offenses === []) {
            $expected = array_map(
                static fn (string $pattern): string => self::headerForYear($pattern, $year),
                array_values((array) Config::get('clery.offense_columns', []))
            );

            throw new ImportException(implode("\n", [
                "The file has no offense columns for {$year}. Expected at least one of: " . implode(', ', $expected) . '.',
                self::yearsHint($csv->headers()),
                'Columns in the file: ' . SchoolImporter::preview($csv->headers()),
                'Fix the patterns in config/unsilenced.php (clery.offense_columns), or pass the year the file actually covers.',
            ]));
        }

        return [
            'unitid' => $unitidColumn,
            'offenses' => $offenses,
            'missing_offenses' => $missing,
        ] + $this->resolveLocation($csv, $locationOption, $fileName);
    }

    public function import(string $path, int $year, ?string $locationOption): array
    {
        $csv = new CsvFile($path);
        $resolved = $this->resolve($csv, $year, $locationOption, basename($path));

        $digits = (int) Config::get('clery.unitid_digits', 6);
        $locationValues = array_change_key_case((array) Config::get('clery.location_values', []), CASE_LOWER);

        $result = SchoolImporter::emptyResult();
        $result['columns_found'] = [
            'unitid' => $resolved['unitid'],
            'offenses' => $resolved['offenses'],
            'missing_offenses' => $resolved['missing_offenses'],
            'location' => $resolved['location'] ?? ('column ' . $resolved['location_column']),
        ];

        $schoolIds = [];
        foreach (Database::connection()->query('SELECT id, unitid FROM schools') as $row) {
            $schoolIds[(int) $row['unitid']] = (int) $row['id'];
        }

        // school_id|location => offense => summed count (null until a number is seen)
        $totals = [];
        $campusRows = [];
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

            $key = $schoolIds[$unitid] . '|' . $location;
            $totals[$key] ??= array_fill_keys(array_keys($resolved['offenses']), null);
            $campusRows[$key] = ($campusRows[$key] ?? 0) + 1;

            foreach ($resolved['offenses'] as $offense => $header) {
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

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            foreach ($totals as $key => $counts) {
                [$schoolId, $location] = explode('|', $key, 2);

                match (CleryStat::upsert((int) $schoolId, $year, $location, $counts)) {
                    1 => $result['rows_added']++,
                    0 => $result['rows_unchanged']++,
                    default => $result['rows_updated']++,
                };
            }

            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }

        $merged = array_sum($campusRows) - count($campusRows);
        $result['message'] = sprintf(
            'Read %s for %d: %s added, %d updated, %d unchanged; %s skipped (%s)%s.',
            Format::plural($result['rows_read'], 'row'),
            $year,
            Format::plural($result['rows_added'], 'school-location row'),
            $result['rows_updated'],
            $result['rows_unchanged'],
            Format::plural($result['rows_skipped'], 'row'),
            Format::plural(count($unknownUnitids), 'unknown UNITID'),
            $merged > 0 ? '; ' . Format::plural($merged, 'extra campus row') . ' summed into their institution' : ''
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

        throw new ImportException(
            "Could not tell which Clery location \"{$fileName}\" covers. Pass --location=" . implode('|', array_keys($labels))
            . ', or add a pattern to clery.location_filename_patterns in config/unsilenced.php.'
        );
    }

    private static function yearsHint(array $headers): string
    {
        $years = [];
        foreach ((array) Config::get('clery.offense_columns', []) as $pattern) {
            $regex = '/^' . strtr(preg_quote((string) $pattern, '/'), ['\{yy\}' => '(\d{2})', '\{yyyy\}' => '(\d{4})']) . '$/i';
            foreach ($headers as $header) {
                if (preg_match($regex, $header, $m)) {
                    $years[$m[1]] = true;
                }
            }
        }

        if ($years === []) {
            return 'No column in the file matches any offense pattern for any year.';
        }

        $found = array_keys($years);
        sort($found);

        return 'The file has offense columns for year suffix(es): ' . implode(', ', $found) . '.';
    }
}
