<?php

namespace Keel\App\Jobs;

use Keel\App\Services\Survivor\CaseDeletionService;

/**
 * Daily: deletes every report rejected more than rejected_retention_days (30)
 * ago, with its case, files and links, exactly as a withdrawal would.
 * Run by `php database/console.php survivor:purge-rejected` from cron, or
 * queued.
 */
class PurgeRejectedReports implements Job
{
    /** @return int how many cases were deleted */
    public function run(): int
    {
        $deletion = new CaseDeletionService();
        $caseIds = CaseDeletionService::expiredRejections();

        foreach ($caseIds as $caseId) {
            $deletion->delete($caseId);
        }

        return count($caseIds);
    }

    public function handle(array $data): void
    {
        $this->run();
    }
}
