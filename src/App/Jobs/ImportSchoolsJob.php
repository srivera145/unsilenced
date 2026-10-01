<?php

namespace Keel\App\Jobs;

use Keel\App\Services\Imports\ImportJobRunner;
use Keel\App\Services\Imports\SchoolImporter;

/**
 * Runs a queued import:schools. Payload: ['import_run_id' => int].
 */
class ImportSchoolsJob implements Job
{
    public function handle(array $data): void
    {
        ImportJobRunner::run((int) ($data['import_run_id'] ?? 0), 'schools', static function (array $run): array {
            return (new SchoolImporter())->import((string) $run['file_path'], $run['data_year'] !== null ? (int) $run['data_year'] : null);
        });
    }
}
