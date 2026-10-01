<?php

declare(strict_types=1);

namespace Tests\Unit;

use Keel\App\Services\Survivor\EvidenceRejected;
use Keel\App\Services\Survivor\SealedText;
use Keel\App\Services\Survivor\VaultKeys;
use Keel\App\Services\Survivor\VaultService;
use PHPUnit\Framework\TestCase;
use Tests\Support\EvidenceFixtures;

/**
 * The vault's encryption: round trips, the SHA-256 fingerprint of the
 * original, and failure on the wrong key, a damaged file or a cut-short file.
 */
class VaultServiceTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $_ENV['VAULT_MASTER_KEY'] = VaultKeys::generate();
        $this->root = sys_get_temp_dir() . '/usv-unit-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        unset($_ENV['VAULT_MASTER_KEY']);
        foreach (glob($this->root . '/*/*') ?: [] as $file) {
            @unlink($file);
        }
        foreach (glob($this->root . '/*') ?: [] as $directory) {
            @rmdir($directory);
        }
        @rmdir($this->root);
    }

    public function testTheMasterKeyMustBeThirtyTwoBytesOfBase64(): void
    {
        self::assertNotNull(VaultKeys::fromString(base64_encode(random_bytes(32))));
        self::assertNotNull(VaultKeys::fromString('base64:' . base64_encode(random_bytes(32))));
        self::assertNull(VaultKeys::fromString(''));
        self::assertNull(VaultKeys::fromString(base64_encode(random_bytes(16))));
        self::assertNull(VaultKeys::fromString('not base64 at all!'));

        $keys = VaultKeys::require();
        self::assertNotSame($keys->text(), $keys->fileKek(), 'one key per purpose');
        self::assertNotSame($keys->text(), $keys->lookup());
    }

    public function testSealedTextRoundTripsAndFailsOutOfContextOrTampered(): void
    {
        $sealed = SealedText::seal('her account, in her words', 'survivor_reports.account');

        self::assertStringStartsWith('v1:', $sealed);
        self::assertStringNotContainsString('account', $sealed);
        self::assertSame('her account, in her words', SealedText::open($sealed, 'survivor_reports.account'));
        self::assertNotSame($sealed, SealedText::seal('her account, in her words', 'survivor_reports.account'), 'a fresh nonce every time');

        $this->assertFails(static fn () => SealedText::open($sealed, 'survivor_reports.published'));

        $raw = base64_decode(substr($sealed, 3));
        $raw[30] = chr(ord($raw[30]) ^ 1);
        $this->assertFails(static fn () => SealedText::open('v1:' . base64_encode($raw), 'survivor_reports.account'));

        $_ENV['VAULT_MASTER_KEY'] = VaultKeys::generate();
        $this->assertFails(static fn () => SealedText::open($sealed, 'survivor_reports.account'));
    }

    public function testAFileRoundTripsAndItsFingerprintIsOfTheOriginalBytes(): void
    {
        $vault = new VaultService($this->root);

        // Empty-ish, exactly one 64 KiB chunk, and several chunks plus a part one.
        foreach (['a', str_repeat('x', 65536), "Notes I made that night.\n" . str_repeat('More notes. ', 20000)] as $bytes) {
            $row = $vault->store(EvidenceFixtures::write($bytes, '.txt'), 'notes.txt');

            self::assertSame('text', $row['kind']);
            self::assertSame(hash('sha256', $bytes), $row['sha256']);
            self::assertSame(strlen($bytes), $row['size_bytes']);
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $row['uploaded_at']);

            $decrypted = $vault->readOriginal($row);
            self::assertSame($bytes, $decrypted);
            self::assertSame($row['sha256'], hash('sha256', $decrypted), 'SHA-256 matches after decrypting');

            $onDisk = (string) file_get_contents($vault->blobPath($row['blob_name']));
            self::assertStringStartsWith('USV1', $onDisk);
            self::assertStringNotContainsString('notes.txt', $onDisk);
            self::assertStringNotContainsString('notes.txt', (string) $row['name_encrypted']);
            self::assertSame('notes.txt', SealedText::open($row['name_encrypted'], 'evidence_files.name'));
            if (strlen($bytes) > 100) {
                self::assertStringNotContainsString(substr($bytes, 0, 24), $onDisk);
            }
        }
    }

    public function testAnyTypeRoundTripsByteForByte(): void
    {
        $vault = new VaultService($this->root);
        $jpeg = EvidenceFixtures::jpegWithGps();
        $row = $vault->store(EvidenceFixtures::write($jpeg, '.jpg'), 'IMG_0001.JPG');

        self::assertSame($jpeg, $vault->readOriginal($row), 'the original keeps every byte, metadata included');
        self::assertSame(hash('sha256', $jpeg), hash('sha256', $vault->readOriginal($row)));
        self::assertNotSame($row['blob_name'], $row['admin_blob_name']);
        self::assertNotSame($row['blob_key'], $row['admin_blob_key']);
    }

    public function testAWrongKeyACutShortFileAndASwappedKeyAllFail(): void
    {
        $vault = new VaultService($this->root);
        $row = $vault->store(EvidenceFixtures::write(str_repeat('evidence text ', 10000), '.txt'), 'a.txt');
        $other = $vault->store(EvidenceFixtures::write('another file', '.txt'), 'b.txt');

        // The file key is bound to its own file name: another row's key will not open it.
        $this->assertFails(static fn () => $vault->readOriginal(['blob_name' => $row['blob_name'], 'blob_key' => $other['blob_key']]));

        // Cut short at a chunk boundary: no final tag, so it is refused rather than returned short.
        $path = $vault->blobPath($row['blob_name']);
        $bytes = (string) file_get_contents($path);
        file_put_contents($path, substr($bytes, 0, 4 + 24 + 65536 + 17));
        $this->assertFails(static fn () => $vault->readOriginal($row));

        // A flipped bit anywhere fails authentication.
        $bytes[100] = chr(ord($bytes[100]) ^ 1);
        file_put_contents($path, $bytes);
        $this->assertFails(static fn () => $vault->readOriginal($row));

        $_ENV['VAULT_MASTER_KEY'] = VaultKeys::generate();
        $this->assertFails(static fn () => $vault->readOriginal($other));
    }

    public function testDeletingRemovesBothCopies(): void
    {
        $vault = new VaultService($this->root);
        $row = $vault->store(EvidenceFixtures::write(EvidenceFixtures::jpegWithGps(), '.jpg'), 'photo.jpg');

        self::assertFileExists($vault->blobPath($row['blob_name']));
        self::assertFileExists($vault->blobPath($row['admin_blob_name']));
        $vault->deleteFiles($row);
        self::assertFileDoesNotExist($vault->blobPath($row['blob_name']));
        self::assertFileDoesNotExist($vault->blobPath($row['admin_blob_name']));
    }

    public function testFilesOverTheLimitAndVaultsInsideTheWebRootAreRefused(): void
    {
        \Keel\App\Support\Config::set('evidence.max_file_bytes', 100);

        try {
            $this->expectException(EvidenceRejected::class);
            (new VaultService($this->root))->store(EvidenceFixtures::write(str_repeat('a', 101), '.txt'), 'big.txt');
        } finally {
            \Keel\App\Support\Config::reset();
        }
    }

    public function testTheVaultMayNotLiveInPublicHtml(): void
    {
        $public = dirname(__DIR__, 2) . '/public_html';

        self::assertFalse(VaultService::rootIsSafe($public));
        self::assertFalse(VaultService::rootIsSafe($public . '/vault'));
        self::assertTrue(VaultService::rootIsSafe(dirname(__DIR__, 2) . '/storage/vault'));
        self::assertTrue(VaultService::rootIsSafe(dirname(__DIR__, 2) . '/public_html_backup'));

        $this->expectException(\RuntimeException::class);
        (new VaultService($public . '/vault'))->store(EvidenceFixtures::write('text', '.txt'), 'a.txt');
    }

    public function testFileNamesAreCleanedForDisplay(): void
    {
        self::assertSame('photo.jpg', VaultService::cleanName('C:\\Users\\me\\photo.jpg', 'jpg'));
        self::assertSame('photo.jpg', VaultService::cleanName('../../photo.jpg', 'jpg'));
        self::assertSame('file.pdf', VaultService::cleanName("\x00\x01", 'pdf'));
        self::assertSame(120, mb_strlen(VaultService::cleanName(str_repeat('é', 300), 'txt')));
    }

    private function assertFails(callable $action): void
    {
        try {
            $action();
        } catch (\RuntimeException) {
            self::assertTrue(true);

            return;
        }

        self::fail('Expected the vault to refuse.');
    }
}
