<?php

declare(strict_types=1);

use Keel\App\Console\Commands\AdminAccessCommand;
use Keel\App\Console\Commands\ClerySpotCheckCommand;
use Keel\App\Console\Commands\ImportCleryCommand;
use Keel\App\Console\Commands\ImportSchoolsCommand;
use Keel\App\Console\Commands\PurgeFixtureSchoolsCommand;
use Keel\App\Console\Commands\SurvivorMaintenanceCommand;
use Keel\App\Console\Commands\VaultKeygenCommand;
use Keel\Core\Env;

require dirname(__DIR__) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

Env::load(dirname(__DIR__));

// Unsilenced's CLI commands. Imports are queued; process them with
//   php database/queue-work.php --once
//
//   php database/console.php import:schools storage/imports/hd2025.csv
//   php database/console.php import:schools storage/imports/drvef2024.csv          (year from the name)
//   php database/console.php import:clery storage/imports/Oncampuscrime222324.csv   (every year in the file)
//   php database/console.php import:clery <file> --headers      (print the file's columns)
//   php database/console.php clery:spot-check 190415             (stored figures next to the raw rows)
//   php database/console.php admin:grant someone@example.org
//   php database/console.php schools:purge-fixtures --dry-run   (then without --dry-run)
//   php database/console.php vault:keygen                       (a new VAULT_MASTER_KEY)
//   php database/console.php vault:check                        (is the vault ready?)
//   php database/console.php survivor:purge-rejected            (daily, cron)
//   php database/console.php survivor:expire-share-links        (hourly, cron)
$commands = [
    'import:schools' => static fn (): ImportSchoolsCommand => new ImportSchoolsCommand(),
    'import:clery' => static fn (): ImportCleryCommand => new ImportCleryCommand(),
    'clery:spot-check' => static fn (): ClerySpotCheckCommand => new ClerySpotCheckCommand(),
    'admin:grant' => static fn (): AdminAccessCommand => new AdminAccessCommand(true),
    'admin:revoke' => static fn (): AdminAccessCommand => new AdminAccessCommand(false),
    'schools:purge-fixtures' => static fn (): PurgeFixtureSchoolsCommand => new PurgeFixtureSchoolsCommand(),
    'vault:keygen' => static fn (): VaultKeygenCommand => new VaultKeygenCommand(false),
    'vault:check' => static fn (): VaultKeygenCommand => new VaultKeygenCommand(true),
    'survivor:purge-rejected' => static fn (): SurvivorMaintenanceCommand => new SurvivorMaintenanceCommand('purge-rejected'),
    'survivor:expire-share-links' => static fn (): SurvivorMaintenanceCommand => new SurvivorMaintenanceCommand('expire-share-links'),
];

$name = $argv[1] ?? '';

if (!isset($commands[$name])) {
    fwrite(STDERR, "Usage: php database/console.php <command> [arguments]\n\nCommands:\n");
    fwrite(STDERR, '  ' . ImportSchoolsCommand::usage() . "\n");
    fwrite(STDERR, '  ' . ImportCleryCommand::usage() . "\n");
    fwrite(STDERR, '  ' . ClerySpotCheckCommand::usage() . "\n");
    fwrite(STDERR, '  ' . AdminAccessCommand::usage() . "\n");
    fwrite(STDERR, '  ' . PurgeFixtureSchoolsCommand::usage() . "\n");
    fwrite(STDERR, '  ' . VaultKeygenCommand::usage() . "\n");
    fwrite(STDERR, '  ' . SurvivorMaintenanceCommand::usage() . "\n");
    exit($name === '' ? 0 : 1);
}

try {
    exit($commands[$name]()->handle(array_slice($argv, 2)));
} catch (\Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
