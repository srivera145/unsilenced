<?php

declare(strict_types=1);

namespace Tests\Unit;

use Keel\App\Services\Survivor\EvidenceRejected;
use Keel\App\Services\Survivor\FileInspector;
use PHPUnit\Framework\TestCase;
use Tests\Support\EvidenceFixtures;

/**
 * File types come from the bytes, never the name. (A renamed file going
 * through the whole upload is in SurvivorReportFlowFeatureTest.)
 */
class FileInspectorTest extends TestCase
{
    public function testEachAllowedTypeIsRecognisedByItsBytes(): void
    {
        self::assertSame('jpeg', FileInspector::detect(EvidenceFixtures::read('photo.jpg')));
        self::assertSame('png', FileInspector::detect(EvidenceFixtures::read('png.png')));
        self::assertSame('m4a', FileInspector::detect(EvidenceFixtures::read('voice.m4a')));
        self::assertSame('mp3', FileInspector::detect(EvidenceFixtures::read('voice.mp3')));
        self::assertSame('pdf', FileInspector::detect(EvidenceFixtures::pdfWithAuthor()));
        self::assertSame('heic', FileInspector::detect(EvidenceFixtures::heicWithExif()['bytes']));
        self::assertSame('text', FileInspector::detect("He texted me at 2am:\r\n\"you up?\"\n\tAnd again at 3."));
        self::assertSame('text', FileInspector::detect("\xEF\xBB\xBFUTF-8 with a BOM, and accents: café"));
    }

    public function testVideoNeverGetsInEvenWhenItLooksLikeAudio(): void
    {
        $this->assertRefused(EvidenceFixtures::read('video.mp4'), 'Video cannot be added');

        // An MP4 with a video track, relabelled with the audio brand.
        $video = EvidenceFixtures::read('video.mp4');
        $relabelled = substr_replace($video, 'M4A ', 8, 4);
        $this->assertRefused($relabelled, 'Video cannot be added');
    }

    public function testOtherTypesAreRefusedWithAdvice(): void
    {
        $this->assertRefused('', 'empty');
        $this->assertRefused("GIF89a\x01\x00\x01\x00", 'GIF');
        $this->assertRefused("PK\x03\x04" . str_repeat("\x00", 30), 'PDF');
        $this->assertRefused("\xFF\xFEh\x00i\x00", 'UTF-16');
        $this->assertRefused("\x00\x01\x02\x03binary", 'cannot be added');
        $this->assertRefused("RIFF\x00\x00\x00\x00WEBPVP8 ", 'cannot be added');
        // AVIF shares HEIC's container but is not on the list.
        $this->assertRefused(EvidenceFixtures::read('photo-gps.avif'), 'Save it as JPEG or PNG');
    }

    public function testAnAacStreamIsNotMistakenForMp3(): void
    {
        // ADTS sync (layer bits 00) is AAC, not MPEG audio.
        $this->assertRefused("\xFF\xF1\x50\x80" . str_repeat("\x00", 20), 'cannot be added');
    }

    private function assertRefused(string $bytes, string $messagePart, bool $allowOtherMessage = false): void
    {
        try {
            FileInspector::detect($bytes);
        } catch (EvidenceRejected $rejected) {
            if (!$allowOtherMessage) {
                self::assertStringContainsStringIgnoringCase($messagePart, $rejected->getMessage());
            }
            self::assertTrue(true);

            return;
        }

        self::fail('Expected the file to be refused: ' . $messagePart);
    }
}
