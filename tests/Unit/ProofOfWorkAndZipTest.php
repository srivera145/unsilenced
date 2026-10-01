<?php

declare(strict_types=1);

namespace Tests\Unit;

use Keel\App\Controllers\ShareLinkController;
use Keel\App\Services\Survivor\ProofOfWork;
use Keel\App\Services\Survivor\ZipStream;
use Keel\Core\Session;
use PHPUnit\Framework\TestCase;

class ProofOfWorkAndZipTest extends TestCase
{
    protected function setUp(): void
    {
        Session::start();
        $_SESSION = [];
    }

    public function testLeadingZeroBits(): void
    {
        self::assertSame(256, ProofOfWork::leadingZeroBits(str_repeat("\x00", 32)));
        self::assertSame(0, ProofOfWork::leadingZeroBits("\x80"));
        self::assertSame(12, ProofOfWork::leadingZeroBits("\x00\x08\xFF"));
    }

    public function testASolvedChallengeVerifiesOnceAndIsThenRememberedAsSpent(): void
    {
        \Keel\App\Support\Config::set('survivor_reports.proof_of_work_bits', 8);
        $challenge = ProofOfWork::challenge();
        $nonce = ProofOfWork::solve($challenge['challenge'], 8);

        self::assertSame($challenge, ProofOfWork::challenge(), 'the same challenge until it is spent');
        self::assertTrue(ProofOfWork::verify($challenge['challenge'], $nonce));
        self::assertGreaterThanOrEqual(8, ProofOfWork::leadingZeroBits(hash('sha256', $challenge['challenge'] . ':' . $nonce, true)));

        self::assertFalse(ProofOfWork::verify($challenge['challenge'], 'abc'));
        self::assertFalse(ProofOfWork::verify(str_repeat('0', 32), $nonce), 'a challenge this session was never given');

        ProofOfWork::consume();
        self::assertFalse(ProofOfWork::verify($challenge['challenge'], $nonce));
        self::assertTrue(ProofOfWork::isSpent($challenge['challenge']));
        self::assertNotSame($challenge['challenge'], ProofOfWork::challenge()['challenge']);
        \Keel\App\Support\Config::reset();
    }

    public function testTheZipOpensWithEveryFileAndTheManifest(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'zip');
        $handle = fopen($path, 'wb');
        $zip = new ZipStream(static function (string $bytes) use ($handle): void {
            fwrite($handle, $bytes);
        });

        $photo = random_bytes(70000);
        $files = [
            ['sha256' => hash('sha256', $photo), 'size_bytes' => strlen($photo), 'uploaded_at' => '2026-10-01 12:00:00'],
            ['sha256' => hash('sha256', 'notes'), 'size_bytes' => 5, 'uploaded_at' => '2026-10-01 12:05:00'],
        ];
        $zip->addFile('photo.jpg', 1790000000, static function (callable $write) use ($photo): void {
            $write(substr($photo, 0, 1000));
            $write(substr($photo, 1000));
        });
        $zip->addString('photo.jpg', 'notes', 1790000000);
        $zip->addString('manifest.txt', ShareLinkController::manifest($files, ['photo.jpg', 'photo (2).jpg'], 1790000000), 1790000000);
        $zip->finish();
        fclose($handle);

        $archive = new \ZipArchive();
        self::assertTrue($archive->open($path, \ZipArchive::CHECKCONS));
        self::assertSame(3, $archive->numFiles);
        self::assertSame($photo, $archive->getFromName('photo.jpg'));
        self::assertSame('notes', $archive->getFromName('photo (2).jpg'), 'a repeated name gets a number');
        $manifest = (string) $archive->getFromName('manifest.txt');
        self::assertStringContainsString('SHA-256:   ' . hash('sha256', $photo), $manifest);
        self::assertStringContainsString('Uploaded:  2026-10-01 12:00:00 UTC', $manifest);
        $archive->close();
        unlink($path);
    }
}
