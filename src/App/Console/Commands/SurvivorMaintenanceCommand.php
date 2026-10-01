<?php

namespace Keel\App\Console\Commands;

use Keel\App\Jobs\ExpireShareLinks;
use Keel\App\Jobs\PurgeRejectedReports;
use Keel\App\Services\Survivor\VaultKeys;
use Keel\Core\Queue;

/**
 * survivor:purge-rejected      delete reports rejected 30+ days ago (daily)
 * survivor:expire-share-links  delete expired share links (hourly)
 *
 * Both run at once (for cron); --queue pushes the job to the queue instead.
 * The output is counts only.
 */
class SurvivorMaintenanceCommand extends Command
{
    public function __construct(private readonly string $task, ?callable $output = null, ?callable $errorOutput = null)
    {
        parent::__construct($output, $errorOutput);
    }

    public static function usage(): string
    {
        return 'survivor:purge-rejected [--queue] | survivor:expire-share-links [--queue]';
    }

    public function handle(array $arguments): int
    {
        [, $options] = $this->parse($arguments);
        $job = $this->task === 'purge-rejected' ? PurgeRejectedReports::class : ExpireShareLinks::class;

        if (isset($options['queue'])) {
            Queue::push($job, []);
            $this->line('Queued ' . $job . '.');

            return 0;
        }

        if ($this->task === 'purge-rejected' && VaultKeys::current() === null) {
            return $this->fail('VAULT_MASTER_KEY is missing or invalid; nothing was deleted.');
        }

        $count = (new $job())->run();
        $this->line($this->task === 'purge-rejected'
            ? "Deleted {$count} rejected report(s) older than the retention period."
            : "Deleted {$count} expired share link(s).");

        return 0;
    }
}
