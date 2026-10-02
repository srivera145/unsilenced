<?php

declare(strict_types=1);

namespace Tests\Feature;

use Keel\App\Console\Commands\VaultRotateCommand;
use Keel\App\Models\EvidenceFile;
use Keel\App\Models\SurvivorCase;
use Keel\App\Models\SurvivorReport;
use Keel\App\Services\Survivor\CaseKeyService;
use Keel\App\Services\Survivor\KeyRotationService;
use Keel\App\Services\Survivor\SealedText;
use Keel\App\Services\Survivor\ShareLinkService;
use Keel\App\Services\Survivor\VaultKeys;
use Keel\App\Services\Survivor\VaultService;
use Keel\Core\Database;
use Tests\Support\EvidenceFixtures;
use Tests\Support\SurvivorHelpers;
use Tests\TestCase;

/**
 * Running the vault (Phase 2.1): the health check, the sodium requirement, and
 * moving everything to a new master key.
 */
class VaultOperationsFeatureTest extends TestCase
{
    use SurvivorHelpers;

    protected function tearDown(): void
    {
        foreach (['VAULT_NEW_MASTER_KEY', 'VAULT_LOOKUP_KEY'] as $key) {
            unset($_ENV[$key], $_SERVER[$key]);
        }

        parent::tearDown();
    }

    public function testUpReportsTheVaultOnlyWhileSubmissionsAreOn(): void
    {
        $this->setEnv('VAULT_MASTER_KEY', '');
        $off = $this->get('/up');
        self::assertSame(200, $off->status, 'submissions off: the vault is not needed');
        self::assertSame(['status' => 'ok', 'database' => true], $off->json());

        $this->setEnv('SUBMISSIONS_ENABLED', 'true');
        $broken = $this->get('/up');
        self::assertSame(503, $broken->status, 'on, without a master key');
        self::assertSame(['status' => 'ok', 'database' => true, 'vault' => false], $broken->json());
        self::assertStringNotContainsString('VAULT', $broken->body, 'never says which setting');

        $this->setEnv('VAULT_MASTER_KEY', 'not-32-bytes');
        self::assertSame(503, $this->get('/up')->status);

        $this->setEnv('VAULT_MASTER_KEY', (string) self::$vaultKey);
        $ready = $this->get('/up');
        self::assertSame(200, $ready->status);
        self::assertSame(['status' => 'ok', 'database' => true, 'vault' => true], $ready->json());

        $this->setEnv('VAULT_PATH', self::$basePath . '/public_html/vault');
        self::assertSame(503, $this->get('/up')->status, 'a vault inside the web root');
    }

    public function testComposerRequiresSodium(): void
    {
        $composer = json_decode((string) file_get_contents(self::$basePath . '/composer.json'), true);
        $lock = json_decode((string) file_get_contents(self::$basePath . '/composer.lock'), true);

        self::assertSame('*', $composer['require']['ext-sodium'] ?? null);
        self::assertSame('*', $lock['platform']['ext-sodium'] ?? null, 'the lock file was updated with it');
        self::assertTrue(extension_loaded('sodium'));
    }

    public function testRotationMovesEveryFileAndTextAndKeepsCaseKeysWorking(): void
    {
        $this->importFixtures();
        $this->enableSubmissions();

        $jpeg = EvidenceFixtures::jpegWithGps();
        $key = (string) $this->submitReport(['email' => 'me@example.org'], [[EvidenceFixtures::write($jpeg, '.jpg'), 'door.jpg']])['key'];
        $case = (new CaseKeyService())->findCase($key);
        $report = SurvivorReport::findByCase((int) $case['id']);
        $admin = $this->createUser();
        SurvivorReport::savePublished((int) $report['id'], 'In my second year', (int) $admin['id']);
        SurvivorReport::write((int) $report['id'], ['admin_note_encrypted' => SurvivorReport::sealNote('Thank you.')]);
        $file = EvidenceFile::forReport((int) $report['id'])[0];
        (new ShareLinkService())->create((int) $case['id'], [(int) $file['id']], 'my attorney', '7d', null);

        $vault = new VaultService();
        $oldBlobs = [$vault->blobPath($file['blob_name']), $vault->blobPath($file['admin_blob_name'])];
        $old = VaultKeys::require();
        $newKey = VaultKeys::generate();
        $new = VaultKeys::fromString($newKey);

        $counts = (new KeyRotationService($old, $new))->run();
        self::assertSame(1, $counts['files_moved']);
        self::assertSame(6, $counts['texts_moved'], 'email, account, published, note, file name, link label');

        // Running it again finds everything done.
        $again = (new KeyRotationService($old, $new))->run();
        self::assertSame(['files_moved' => 0, 'files_already' => 1, 'texts_moved' => 0, 'texts_already' => 6], $again);

        foreach ($oldBlobs as $path) {
            self::assertFileDoesNotExist($path, 'old encrypted files are deleted');
        }

        // Switch .env as the command says: the new master, the old lookup key.
        $lookup = $old->lookupForEnv();
        $this->setEnv('VAULT_MASTER_KEY', $newKey);
        $this->setEnv('VAULT_LOOKUP_KEY', $lookup);

        self::assertSame((int) $case['id'], (int) (new CaseKeyService())->findCase($key)['id'], 'the key still opens the report');
        $report = SurvivorReport::find((int) $report['id']);
        self::assertSame(self::ACCOUNT, SurvivorReport::account($report));
        self::assertSame('In my second year', SurvivorReport::published($report));
        self::assertSame('Thank you.', SurvivorReport::adminNote($report));
        self::assertSame('me@example.org', SurvivorCase::email(SurvivorCase::find((int) $case['id'])));
        $file = EvidenceFile::find((int) $file['id']);
        self::assertSame('door.jpg', EvidenceFile::name($file));
        self::assertSame($jpeg, $vault->readOriginal($file), 'the original, byte for byte');
        self::assertSame($file['sha256'], hash('sha256', $vault->readOriginal($file)));
        self::assertArrayNotHasKey('GPS', EvidenceFixtures::exif($vault->readAdminCopy($file)));
        self::assertSame('my attorney', ShareLinkService::label(Database::connection()->query('SELECT * FROM share_links')->fetch()));

        // The old master opens nothing any more.
        $this->setEnv('VAULT_MASTER_KEY', (string) self::$vaultKey);
        $this->expectException(\RuntimeException::class);
        SurvivorReport::account($report);
    }

    public function testTheCommandInsistsOnTheNewKeyAndOnSubmissionsBeingOff(): void
    {
        $lines = [];
        $errors = [];
        $command = static function () use (&$lines, &$errors): VaultRotateCommand {
            return new VaultRotateCommand(
                static function (string $line) use (&$lines): void { $lines[] = $line; },
                static function (string $line) use (&$errors): void { $errors[] = $line; }
            );
        };

        self::assertSame(1, $command()->handle(['--confirm']));
        self::assertStringContainsString('VAULT_NEW_MASTER_KEY', implode("\n", $errors), 'no new key yet');

        $this->setEnv('VAULT_NEW_MASTER_KEY', (string) self::$vaultKey);
        $errors = [];
        self::assertSame(1, $command()->handle(['--confirm']));
        self::assertStringContainsString('the same as VAULT_MASTER_KEY', implode("\n", $errors));

        $this->setEnv('VAULT_NEW_MASTER_KEY', VaultKeys::generate());
        $this->setEnv('SUBMISSIONS_ENABLED', 'true');
        $errors = [];
        self::assertSame(1, $command()->handle(['--confirm']));
        self::assertStringContainsString('SUBMISSIONS_ENABLED=false', implode("\n", $errors));

        $this->setEnv('SUBMISSIONS_ENABLED', 'false');
        $lines = [];
        self::assertSame(0, $command()->handle([]));
        self::assertStringContainsString('Nothing changed', implode("\n", $lines), 'without --confirm it only reports');

        $lines = [];
        self::assertSame(0, $command()->handle(['--confirm']));
        $output = implode("\n", $lines);
        self::assertStringContainsString('VAULT_LOOKUP_KEY=' . VaultKeys::require()->lookupForEnv(), $output);
        self::assertStringNotContainsString((string) $_ENV['VAULT_NEW_MASTER_KEY'], $output, 'the new key is never printed');
        self::assertStringNotContainsString((string) self::$vaultKey, $output);
    }

    public function testEveryEncryptedColumnIsInTheRotationList(): void
    {
        $columns = [];
        foreach (Database::connection()->query("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME LIKE '%\\_encrypted'")->fetchAll() as $row) {
            $columns[] = $row['TABLE_NAME'] . '.' . $row['COLUMN_NAME'];
        }
        sort($columns);

        $listed = array_map(static fn (array $c): string => $c[0] . '.' . $c[1], SealedText::COLUMNS);
        sort($listed);

        self::assertSame($columns, $listed, 'a new encrypted column must be added to SealedText::COLUMNS, or rotation would leave it under the old key');
    }
}
