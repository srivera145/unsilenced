<?php

declare(strict_types=1);

namespace Tests\Unit;

use Keel\App\Services\Survivor\Isobmff;
use Keel\App\Services\Survivor\MetadataStripper;
use PHPUnit\Framework\TestCase;
use Tests\Support\EvidenceFixtures;

/**
 * The admin copy: what identifies the person who made a file is gone, and
 * what is needed to show or play it is untouched.
 */
class MetadataStripperTest extends TestCase
{
    public function testJpegLosesExifGpsXmpAndCommentsButKeepsTheImage(): void
    {
        $original = EvidenceFixtures::jpegWithGps();
        $before = EvidenceFixtures::exif($original);
        self::assertArrayHasKey('GPS', $before, 'the fixture really has GPS');
        self::assertSame('40/1', $before['GPS']['GPSLatitude'][0]);

        $result = MetadataStripper::strip('jpeg', $original);
        $copy = (string) $result['bytes'];
        $after = EvidenceFixtures::exif($copy);

        self::assertSame(MetadataStripper::STRIPPED, $result['status']);
        self::assertArrayNotHasKey('GPS', $after);
        self::assertArrayNotHasKey('IFD0', $after);
        self::assertArrayNotHasKey('EXIF', $after);
        self::assertArrayNotHasKey('COMMENT', $after);
        self::assertStringNotContainsString(EvidenceFixtures::PERSON, $copy);
        self::assertStringNotContainsString(EvidenceFixtures::CAMERA, $copy);
        self::assertStringNotContainsString('Exif', $copy);
        self::assertStringNotContainsString('xmpmeta', $copy);
        self::assertSame(6, $result['orientation'], 'orientation is kept aside to show it upright');

        // Tables, frame and scan: byte for byte the same image data.
        self::assertSame(substr($original, strpos($original, "\xFF\xDA")), substr($copy, strpos($copy, "\xFF\xDA")));
        self::assertStringStartsWith("\xFF\xD8", $copy);
        self::assertStringEndsWith("\xFF\xD9", $copy);
    }

    public function testJpegDropsAnythingAppendedAfterTheImage(): void
    {
        $original = EvidenceFixtures::jpegWithGps() . "\xFF\xD8appended second image by " . EvidenceFixtures::PERSON;
        $copy = (string) MetadataStripper::strip('jpeg', $original)['bytes'];

        self::assertStringNotContainsString(EvidenceFixtures::PERSON, $copy);
        self::assertStringEndsWith("\xFF\xD9", $copy);
    }

    public function testPngKeepsOnlyImageAndColourChunks(): void
    {
        $original = EvidenceFixtures::pngWithMetadata();
        $result = MetadataStripper::strip('png', $original);
        $copy = (string) $result['bytes'];

        self::assertSame(MetadataStripper::STRIPPED, $result['status']);
        self::assertSame(6, $result['orientation']);
        foreach (['tEXt', 'eXIf', 'tIME', EvidenceFixtures::PERSON, EvidenceFixtures::CAMERA, 'TRAILING'] as $gone) {
            self::assertStringNotContainsString($gone, $copy, $gone);
        }
        self::assertStringContainsString('IHDR', $copy);
        self::assertStringContainsString('IDAT', $copy);
        self::assertStringEndsWith('IEND' . pack('N', crc32('IEND')), $copy);
        self::assertSame(EvidenceFixtures::read('png.png'), $copy, 'exactly the image, chunk for chunk');
    }

    public function testHeicExifItemIsZeroedInPlaceAndTheImageItemUntouched(): void
    {
        $heic = EvidenceFixtures::heicWithExif();
        $original = $heic['bytes'];
        self::assertStringContainsString(EvidenceFixtures::CAMERA, $original);

        $result = MetadataStripper::strip('heic', $original);
        $copy = (string) $result['bytes'];

        self::assertSame(MetadataStripper::STRIPPED, $result['status']);
        self::assertSame(strlen($original), strlen($copy), 'every offset in the file stays valid');
        self::assertSame(str_repeat("\x00", $heic['exif_length']), substr($copy, $heic['exif_offset'], $heic['exif_length']));
        self::assertSame(substr($original, $heic['image_offset'], $heic['image_length']), substr($copy, $heic['image_offset'], $heic['image_length']));
        self::assertStringNotContainsString(EvidenceFixtures::CAMERA, $copy);
        self::assertStringNotContainsString('Exif', $copy, 'the item is renamed so readers skip it');
    }

    public function testARealLibheifFileLosesItsExifItem(): void
    {
        // photo-gps.avif: photo-gps.jpg converted by ImageMagick/libheif, which
        // copied the GPS EXIF into a HEIF Exif item. HEIC uses the same boxes.
        $original = EvidenceFixtures::read('photo-gps.avif');
        self::assertStringContainsString(EvidenceFixtures::CAMERA, $original);

        $result = MetadataStripper::strip('heic', $original);
        $copy = (string) $result['bytes'];

        self::assertSame(MetadataStripper::STRIPPED, $result['status']);
        self::assertSame(strlen($original), strlen($copy));
        self::assertStringNotContainsString(EvidenceFixtures::CAMERA, $copy);
        self::assertStringNotContainsString("MM\x00\x2A", $copy, 'no TIFF header left');
    }

    public function testM4aTagsBecomeFreeSpaceAndTheAudioStillParses(): void
    {
        $original = EvidenceFixtures::read('voice.m4a');
        self::assertStringContainsString(EvidenceFixtures::PERSON, $original);

        $result = MetadataStripper::strip('m4a', $original);
        $copy = (string) $result['bytes'];

        self::assertSame(MetadataStripper::STRIPPED, $result['status']);
        self::assertSame(strlen($original), strlen($copy), 'chunk offsets stay valid');
        self::assertStringNotContainsString(EvidenceFixtures::PERSON, $copy);
        self::assertStringNotContainsString('Fixture title', $copy);
        self::assertStringNotContainsString('udta', $copy);
        self::assertSame(['soun'], Isobmff::trackHandlers($copy));

        $mdat = Isobmff::find(Isobmff::boxes($original), 'mdat');
        self::assertSame(substr($original, $mdat['start'], $mdat['end'] - $mdat['start']), substr($copy, $mdat['start'], $mdat['end'] - $mdat['start']), 'the audio itself is unchanged');
    }

    public function testMp3LosesItsTagsAtBothEnds(): void
    {
        $original = EvidenceFixtures::read('voice.mp3');
        self::assertStringStartsWith('ID3', $original);
        self::assertSame('TAG', substr($original, -128, 3));

        $copy = (string) MetadataStripper::strip('mp3', $original)['bytes'];

        self::assertStringNotContainsString(EvidenceFixtures::PERSON, $copy);
        self::assertStringNotContainsString('Fixture title', $copy);
        self::assertSame(0xFF, ord($copy[0]), 'starts at the first audio frame');
        self::assertSame(0xE0, ord($copy[1]) & 0xE0);
        self::assertNotSame('TAG', substr($copy, -128, 3));
    }

    public function testPdfAuthorAndXmpAreBlankedWithoutMovingAnything(): void
    {
        $original = EvidenceFixtures::pdfWithAuthor();
        $result = MetadataStripper::strip('pdf', $original);
        $copy = (string) $result['bytes'];

        self::assertSame(MetadataStripper::STRIPPED, $result['status']);
        self::assertSame(strlen($original), strlen($copy));
        self::assertStringNotContainsString(EvidenceFixtures::PERSON, $copy);
        self::assertStringNotContainsString('D:20260301', $copy);
        self::assertStringNotContainsString('FEFF0046', $copy);
        self::assertStringContainsString('0 0 1 rg 20 20 160 160 re f', $copy, 'the page is untouched');

        // The cross-reference table still points at each object.
        preg_match_all('/^(\d{10}) 00000 n /m', $copy, $offsets);
        foreach ($offsets[1] as $index => $offset) {
            self::assertStringStartsWith(($index + 1) . ' 0 obj', substr($copy, (int) $offset));
        }
    }

    public function testAPdfWithCompressedObjectsIsMarkedPartial(): void
    {
        $pdf = str_replace('/Type /Pages', '/Type /Pages /Note /ObjStm', EvidenceFixtures::pdfWithAuthor());

        self::assertSame(MetadataStripper::PARTIAL, MetadataStripper::strip('pdf', $pdf)['status']);
    }

    public function testTextIsUnchangedAndUnparseableFilesHaveNoAdminCopy(): void
    {
        self::assertSame("plain notes\n", MetadataStripper::strip('text', "plain notes\n")['bytes']);

        $broken = MetadataStripper::strip('heic', "\x00\x00\x00\x18ftypheic\x00\x00\x00\x00mif1heic");
        self::assertSame(MetadataStripper::UNAVAILABLE, $broken['status']);
        self::assertNull($broken['bytes']);
    }
}
