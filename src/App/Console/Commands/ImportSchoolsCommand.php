<?php

namespace Keel\App\Console\Commands;

use Keel\App\Jobs\ImportSchoolsJob;
use Keel\App\Models\ImportRun;
use Keel\App\Services\Imports\ImportException;
use Keel\App\Services\Imports\SchoolImporter;
use Keel\App\Support\CsvFile;
use Keel\Core\Queue;

/**
 * import:schools — queue an IPEDS institution file.
 *
 * The file's headers are checked here, before anything is queued, so a wrong
 * file or a wrong column map fails at the prompt rather than later in the
 * worker. The import itself runs in ImportSchoolsJob.
 */
class ImportSchoolsCommand extends Command
{
    public static function usage(): string
    {
        return 'import:schools <file> [--year=YYYY] [--now] [--headers]';
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

            $importer = new SchoolImporter();
            $resolved = $importer->resolveColumns($csv);

            $optionYear = null;
            if (isset($options['year'])) {
                if (!is_string($options['year']) || !ctype_digit($options['year'])) {
                    return $this->fail('--year must be a four-digit year.');
                }
                $optionYear = (int) $options['year'];
            }

            $year = $importer->resolveYear($resolved, $optionYear, basename($path));
        } catch (ImportException $exception) {
            return $this->fail("import:schools refused {$path}\n" . $exception->getMessage());
        }

        $runId = ImportRun::create([
            'kind' => 'schools',
            'file_name' => basename($path),
            'file_path' => $path,
            'file_sha256' => (string) hash_file('sha256', $path),
            'data_year' => $year,
        ]);

        $this->line(sprintf(
            'Import run #%d: %s (profiles: %s%s)',
            $runId,
            basename($path),
            implode(', ', $resolved['profiles']),
            $year !== null ? ", enrollment year {$year}" : ''
        ));

        if (isset($options['now'])) {
            (new ImportSchoolsJob())->handle(['import_run_id' => $runId]);

            return $this->report($runId);
        }

        Queue::push(ImportSchoolsJob::class, ['import_run_id' => $runId]);
        $this->line('Queued. Run the worker to process it: php database/queue-work.php --once');

        return 0;
    }
}
