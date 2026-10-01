<?php

namespace Keel\App\Console\Commands;

use Keel\App\Services\Survivor\VaultKeys;
use Keel\App\Services\Survivor\VaultService;
use Keel\App\Support\Submissions;

/**
 * vault:keygen  prints a new VAULT_MASTER_KEY for .env. Writes nothing.
 * vault:check   says whether the vault is set up to take reports.
 */
class VaultKeygenCommand extends Command
{
    public function __construct(private readonly bool $check = false, ?callable $output = null, ?callable $errorOutput = null)
    {
        parent::__construct($output, $errorOutput);
    }

    public static function usage(): string
    {
        return 'vault:keygen | vault:check';
    }

    public function handle(array $arguments): int
    {
        if (!$this->check) {
            $this->line('VAULT_MASTER_KEY=' . VaultKeys::generate());
            $this->line('Put this in .env (never in git or a backup). Keep a copy somewhere safe and offline:');
            $this->line('without it, every evidence file and account is unreadable.');

            return 0;
        }

        $this->line('SUBMISSIONS_ENABLED: ' . (Submissions::enabled() ? 'true' : 'false'));
        $this->line('Vault directory: ' . VaultService::root());
        $problem = Submissions::problem();

        if ($problem !== null) {
            return $this->fail('Not ready: ' . $problem . '.');
        }

        $this->line('Ready: the key is valid, sodium is loaded and the vault is outside public_html.');

        return 0;
    }
}
