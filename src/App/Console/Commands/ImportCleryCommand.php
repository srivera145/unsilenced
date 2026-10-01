<?php

namespace Keel\App\Console\Commands;

use Keel\App\Jobs\ImportCleryJob;
use Keel\App\Models\ImportRun;
use Keel\App\Services\Imports\CleryImporter;
use Keel\App\Services\Imports\ImportException;
use Keel\App\Support\CsvFile;
use Keel\Core\Queue;

/**
 * import:clery — queue one year of one Campus Safety and Security data file.
 *
 *   php database/console.php import:clery storage/imports/oncampuscrime.csv 2023
 *
 * The location type comes from --location, a configured location column, or
 * the file name, in that order. Headers are checked before anything is queued.
 */
class ImportCleryCommand extends Command
{
    public static function usage(): string
    {
        return 'import:clery <file> <year> [--location=on_campus|on_campus_housing|noncampus|public_property] [--now] [--headers]';
    }

    public function handle(array $arguments): int
    {
        [$positional, $options] = $this->parse($arguments);

        if (!isset($positional[0])) {
            return $this->fail('Usage: php database/console.php ' . self::usage());
        }

        $path = $this->resolvePath($positional[0]);
        if ($path === null) {
            return $this->fail("File not found: {$positional[0]}");
        }

        try {
            $csv = new CsvFile($path);

            if (isset($options['headers'])) {
                $this->printHeaders($csv);

                return 0;
            }

            if (!isset($positional[1]) || !ctype_digit($positional[1]) || strlen($positional[1]) !== 4) {
                return $this->fail("A four-digit data year is required.\nUsage: php database/console.php " . self::usage());
            }

            $year = (int) $positional[1];
            $location = isset($options['location']) && is_string($options['location']) ? $options['location'] : null;
            $resolved = (new CleryImporter())->resolve($csv, $year, $location, basename($path));
        } catch (ImportException $exception) {
            return $this->fail("import:clery refused {$path}\n" . $exception->getMessage());
        }

        $runId = ImportRun::create([
            'kind' => 'clery',
            'file_name' => basename($path),
            'file_path' => $path,
            'file_sha256' => (string) hash_file('sha256', $path),
            'data_year' => $year,
            'location' => $resolved['location'] ?? $location,
        ]);

        $this->line(sprintf(
            'Import run #%d: %s, %d, location %s, offenses %s',
            $runId,
            basename($path),
            $year,
            $resolved['location'] ?? ('from column ' . $resolved['location_column']),
            implode(', ', array_keys($resolved['offenses']))
        ));

        if (isset($options['now'])) {
            (new ImportCleryJob())->handle(['import_run_id' => $runId]);

            return $this->report($runId);
        }

        Queue::push(ImportCleryJob::class, ['import_run_id' => $runId]);
        $this->line('Queued. Run the worker to process it: php database/queue-work.php --once');

        return 0;
    }
}
