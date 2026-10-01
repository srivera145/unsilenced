<?php

namespace Keel\App\Jobs;

use Keel\App\Services\Imports\CleryImporter;
use Keel\App\Services\Imports\ImportJobRunner;

/**
 * Runs a queued import:clery. Payload: ['import_run_id' => int].
 */
class ImportCleryJob implements Job
{
    public function handle(array $data): void
    {
        ImportJobRunner::run((int) ($data['import_run_id'] ?? 0), 'clery', static function (array $run): array {
            return (new CleryImporter())->import((string) $run['file_path'], (int) $run['data_year'], $run['location'] ?? null);
        });
    }
}
