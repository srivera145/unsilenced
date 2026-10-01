<?php

namespace Keel\App\Jobs;

use Keel\App\Services\Survivor\ShareLinkService;

/**
 * Deletes share links past their expiry. An expired link already refuses to
 * open (ShareLinkService::usable() checks the time on every request); this
 * removes the rows, so nothing of it is kept. Hourly from cron:
 * `php database/console.php survivor:expire-share-links`.
 */
class ExpireShareLinks implements Job
{
    /** @return int how many links were deleted */
    public function run(): int
    {
        return (new ShareLinkService())->deleteExpired();
    }

    public function handle(array $data): void
    {
        $this->run();
    }
}
