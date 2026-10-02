<?php

namespace Keel\App\Console\Commands;

use Keel\App\Services\Survivor\KeyRotationService;
use Keel\App\Services\Survivor\VaultKeys;
use Keel\App\Support\Submissions;
use Keel\Core\Database;
use Keel\Core\Env;

/**
 * vault:rotate [--confirm]
 *
 * Moves the vault to a new master key: the emergency step when
 * VAULT_MASTER_KEY may have leaked. The procedure, in LAUNCH-CHECKLIST.md:
 *
 *   1. SUBMISSIONS_ENABLED=false (this command refuses otherwise) and pause
 *      moderation.
 *   2. vault:keygen, and put the new key in .env as VAULT_NEW_MASTER_KEY.
 *      Never on the command line, where it would land in shell history.
 *   3. vault:rotate shows what it will do; vault:rotate --confirm does it.
 *      Safe to run again if it stops part-way.
 *   4. Change .env as it then says, vault:check, take a new backup, turn
 *      submissions back on.
 */
class VaultRotateCommand extends Command
{
    public static function usage(): string
    {
        return 'vault:rotate [--confirm]   (needs VAULT_NEW_MASTER_KEY in .env and SUBMISSIONS_ENABLED=false)';
    }

    public function handle(array $arguments): int
    {
        [, $options] = $this->parse($arguments);

        $old = VaultKeys::current();
        if ($old === null) {
            return $this->fail('VAULT_MASTER_KEY (or VAULT_LOOKUP_KEY, if set) is missing or invalid: there is nothing to rotate from.');
        }

        $new = VaultKeys::fromString((string) Env::get('VAULT_NEW_MASTER_KEY', ''));
        if ($new === null) {
            return $this->fail("Put the new key in .env first:\n  php database/console.php vault:keygen\nand add its value as VAULT_NEW_MASTER_KEY=... (not on the command line).");
        }

        if ($old->sameMasterAs($new)) {
            return $this->fail('VAULT_NEW_MASTER_KEY is the same as VAULT_MASTER_KEY.');
        }

        if (Submissions::enabled()) {
            return $this->fail('Set SUBMISSIONS_ENABLED=false first: while this runs the site cannot read what has moved. Pause moderation too.');
        }

        $connection = Database::connection();
        $files = (int) $connection->query('SELECT COUNT(*) FROM evidence_files')->fetchColumn();
        $this->line("Evidence files to re-encrypt: {$files} (both copies of each, with new file keys).");
        $this->line('Encrypted text values (accounts, notes, file names, labels, emails) to re-seal.');

        if (!isset($options['confirm'])) {
            $this->line('');
            $this->line('Nothing changed. Run again with --confirm to rotate. Take a backup first.');

            return 0;
        }

        $counts = (new KeyRotationService($old, $new))->run();

        $this->line(sprintf(
            'Done: %d files re-encrypted (%d already were), %d text values re-sealed (%d already were).',
            $counts['files_moved'],
            $counts['files_already'],
            $counts['texts_moved'],
            $counts['texts_already']
        ));
        $this->line('');
        $this->line('Now change .env:');
        $this->line('  1. Set VAULT_MASTER_KEY to the value of VAULT_NEW_MASTER_KEY, then delete VAULT_NEW_MASTER_KEY.');
        if (trim((string) Env::get('VAULT_LOOKUP_KEY', '')) === '') {
            $this->line('  2. Add this line, which keeps existing case keys working (it decrypts nothing):');
            $this->line('     VAULT_LOOKUP_KEY=' . $old->lookupForEnv());
        } else {
            $this->line('  2. Keep VAULT_LOOKUP_KEY as it is.');
        }
        $this->line('Then: php database/console.php vault:check, take a new backup, replace the offline copy of the key,');
        $this->line('and turn submissions back on. Backups made before now still open with the old key: treat them as exposed.');

        return 0;
    }
}
