<?php

namespace Keel\App\Services\Imports;

use Keel\App\Models\School;
use Keel\App\Support\Config;
use Keel\App\Support\CsvFile;
use Keel\Core\Database;

/**
 * Imports IPEDS institution files, keyed on UNITID.
 *
 * Which fields a file provides is decided by config ipeds.profiles: the
 * directory file (name, city, state, control) creates and updates schools; the
 * enrollment file updates enrollment on schools that already exist. A file must
 * satisfy at least one profile in full or nothing is imported.
 *
 * Idempotent: a school is matched on UNITID and updated in place, so running the
 * same file again changes nothing and adds nothing.
 */
class SchoolImporter
{
    /**
     * @return array{fields: array<string, string>, profiles: list<string>}
     * @throws ImportException when no profile's columns are all present
     */
    public function resolveColumns(CsvFile $csv): array
    {
        $map = (array) Config::get('ipeds.columns', []);
        $profiles = (array) Config::get('ipeds.profiles', []);

        $fields = [];
        foreach ($map as $field => $header) {
            if ($csv->has((string) $header)) {
                $fields[$field] = (string) $header;
            }
        }

        $matched = [];
        $missingByProfile = [];
        foreach ($profiles as $profile => $required) {
            $missing = array_values(array_diff($required, array_keys($fields)));
            if ($missing === []) {
                $matched[] = $profile;
            } else {
                $missingByProfile[$profile] = $missing;
            }
        }

        if ($matched === []) {
            $lines = ['The file is missing required columns. It must contain every column of at least one IPEDS profile:'];
            foreach ($missingByProfile as $profile => $missing) {
                $names = array_map(static fn (string $field): string => '"' . ($map[$field] ?? $field) . "\" ({$field})", $missing);
                $lines[] = "  {$profile}: missing " . implode(', ', $names);
            }
            $lines[] = 'Columns in the file: ' . self::preview($csv->headers());
            $lines[] = 'Fix the column names in config/unsilenced.php (ipeds.columns), or check this is the right file.';

            throw new ImportException(implode("\n", $lines));
        }

        return ['fields' => $fields, 'profiles' => $matched];
    }

    /**
     * The enrollment year: --year if given, otherwise a four-digit year in the
     * file name (HD2023.csv, DRVEF2023.csv). Only needed for enrollment files.
     */
    public function resolveYear(array $resolved, ?int $optionYear, string $fileName): ?int
    {
        if (!in_array('enrollment', $resolved['profiles'], true)) {
            return null;
        }

        $year = $optionYear;
        if ($year === null && preg_match('/(?<!\d)(19|20)\d{2}(?!\d)/', $fileName, $m)) {
            $year = (int) $m[0];
        }

        if ($year === null) {
            throw new ImportException(
                "This file has enrollment figures but no year. Pass --year=YYYY (the IPEDS collection year) so each school page can say which year its enrollment is from."
            );
        }

        self::assertYear($year);

        return $year;
    }

    public function import(string $path, ?int $enrollmentYear): array
    {
        $csv = new CsvFile($path);
        $resolved = $this->resolveColumns($csv);
        $fields = $resolved['fields'];
        $hasDirectory = in_array('directory', $resolved['profiles'], true);
        $hasEnrollment = in_array('enrollment', $resolved['profiles'], true);

        if ($hasEnrollment && $enrollmentYear === null) {
            throw new ImportException('Enrollment year missing for an enrollment file.');
        }

        $controls = (array) Config::get('ipeds.control_values', []);
        $jurisdictions = Config::allJurisdictions();

        $result = self::emptyResult();
        $result['columns_found'] = ['profiles' => $resolved['profiles'], 'fields' => $fields];

        $pdo = Database::connection();
        [$schoolIds, $slugsByState] = $this->preload();

        $pdo->beginTransaction();

        try {
            foreach ($csv->rows() as $line => $row) {
                $result['rows_read']++;

                $unitid = self::parseUnitid(CsvFile::value($row, $fields['unitid']));
                if ($unitid === null) {
                    self::skip($result, $line, 'missing or invalid UNITID "' . CsvFile::value($row, $fields['unitid']) . '"');
                    continue;
                }

                $attributes = [];

                if ($hasDirectory) {
                    $name = CsvFile::value($row, $fields['name']);
                    $state = strtoupper(CsvFile::value($row, $fields['state']));

                    if ($name === '') {
                        self::skip($result, $line, "UNITID {$unitid}: no institution name");
                        continue;
                    }

                    if (!isset($jurisdictions[$state])) {
                        self::skip($result, $line, "UNITID {$unitid}: unknown state code \"{$state}\"");
                        continue;
                    }

                    $city = CsvFile::value($row, $fields['city']);
                    $attributes['name'] = mb_substr($name, 0, 255);
                    $attributes['city'] = $city === '' ? null : mb_substr($city, 0, 120);
                    $attributes['state'] = $state;
                    $attributes['control'] = $controls[CsvFile::value($row, $fields['control'])] ?? null;
                }

                if ($hasEnrollment) {
                    $raw = CsvFile::value($row, $fields['enrollment']);
                    if (ctype_digit($raw)) {
                        $attributes['enrollment'] = (int) $raw;
                        $attributes['enrollment_year'] = $enrollmentYear;
                    }
                }

                if ($attributes === []) {
                    self::skip($result, $line, "UNITID {$unitid}: no enrollment figure in this row");
                    continue;
                }

                try {
                    if (isset($schoolIds[$unitid])) {
                        $changed = School::update($schoolIds[$unitid], $attributes);
                        $changed > 0 ? $result['rows_updated']++ : $result['rows_unchanged']++;
                        continue;
                    }

                    if (!$hasDirectory) {
                        self::skip($result, $line, "UNITID {$unitid}: no school with this UNITID yet. Import the IPEDS directory file first.");
                        continue;
                    }

                    $state = $attributes['state'];
                    $slug = School::uniqueSlug($attributes['name'], $attributes['city'], $unitid, $slugsByState[$state] ?? []);
                    $schoolIds[$unitid] = School::create(['unitid' => $unitid, 'slug' => $slug] + $attributes);
                    $slugsByState[$state][$slug] = true;
                    $result['rows_added']++;
                } catch (\PDOException $exception) {
                    if ((string) $exception->getCode() !== '23000') {
                        throw $exception;
                    }

                    self::skip($result, $line, "UNITID {$unitid}: conflicts with another school (" . $exception->getMessage() . ')');
                }
            }

            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }

        $result['message'] = sprintf(
            'Read %s: %d added, %d updated, %d unchanged, %d skipped.',
            \Keel\App\Support\Format::plural($result['rows_read'], 'row'),
            $result['rows_added'],
            $result['rows_updated'],
            $result['rows_unchanged'],
            $result['rows_skipped']
        );

        return $result;
    }

    /** @return array{0: array<int, int>, 1: array<string, array<string, true>>} */
    private function preload(): array
    {
        $ids = [];
        $slugs = [];

        foreach (Database::connection()->query('SELECT id, unitid, state, slug FROM schools') as $row) {
            $ids[(int) $row['unitid']] = (int) $row['id'];
            $slugs[(string) $row['state']][(string) $row['slug']] = true;
        }

        return [$ids, $slugs];
    }

    public static function emptyResult(): array
    {
        return [
            'rows_read' => 0,
            'rows_added' => 0,
            'rows_updated' => 0,
            'rows_unchanged' => 0,
            'rows_skipped' => 0,
            'error_count' => 0,
            'errors' => [],
            'columns_found' => [],
            'message' => null,
        ];
    }

    public static function skip(array &$result, int $line, string $message): void
    {
        $result['rows_skipped']++;
        $result['error_count']++;

        if (count($result['errors']) < \Keel\App\Models\ImportRun::MAX_STORED_ERRORS) {
            $result['errors'][] = "Line {$line}: {$message}";
        }
    }

    public static function parseUnitid(string $value, int $digits = 6): ?int
    {
        $value = trim($value);
        if ($value === '' || !ctype_digit($value)) {
            return null;
        }

        if (strlen($value) > $digits) {
            $value = substr($value, 0, $digits);
        }

        $unitid = (int) $value;

        return $unitid > 0 ? $unitid : null;
    }

    public static function assertYear(int $year): void
    {
        $max = (int) date('Y') + 1;
        if ($year < 1990 || $year > $max) {
            throw new ImportException("Year {$year} is out of range (1990 to {$max}).");
        }
    }

    public static function preview(array $headers, int $limit = 40): string
    {
        $shown = array_slice($headers, 0, $limit);
        $more = count($headers) - count($shown);

        return implode(', ', $shown) . ($more > 0 ? " … and {$more} more" : '');
    }
}
