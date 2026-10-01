<?php

namespace Keel\App\Services\Imports;

use Keel\App\Models\ImportRun;

/**
 * The bookkeeping both import jobs share: load the run, refuse a file that
 * changed after it was queued, record the outcome.
 *
 * A problem with the file (ImportException) fails the run and returns, because
 * the worker's retries would fail identically. Anything else (the database went
 * away, say) fails the run and rethrows so the worker retries; the import is
 * idempotent, so a retry that succeeds leaves the same data a first-time
 * success would have.
 */
final class ImportJobRunner
{
    /** @param callable(array): array $import */
    public static function run(int $runId, string $kind, callable $import): void
    {
        $run = ImportRun::find($runId);

        if ($run === null || $run['kind'] !== $kind) {
            throw new \RuntimeException("Import run {$runId} ({$kind}) not found.");
        }

        if ($run['status'] === 'complete') {
            return;
        }

        ImportRun::markRunning($runId);

        try {
            $path = (string) $run['file_path'];

            if (!is_file($path)) {
                throw new ImportException("The file is no longer at {$path}.");
            }

            if (hash_file('sha256', $path) !== $run['file_sha256']) {
                throw new ImportException('The file changed after this import was queued. Queue it again so the run records what was actually imported.');
            }

            ImportRun::finish($runId, 'complete', $import($run));
        } catch (ImportException $exception) {
            ImportRun::fail($runId, $exception->getMessage());
        } catch (\Throwable $exception) {
            ImportRun::fail($runId, 'Unexpected error: ' . $exception->getMessage());

            throw $exception;
        }
    }
}
