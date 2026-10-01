<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Evidence files for tests, with known metadata planted in them.
 *
 * tests/fixtures/evidence/ holds tiny real files made with ImageMagick and
 * ffmpeg (a gradient, a tone, four frames of a test pattern): photo.jpg and
 * png.png have no metadata of their own, so these helpers add it; voice.m4a
 * and voice.mp3 carry ffmpeg's title and artist tags; photo-gps.avif is
 * photo-gps.jpg converted by libheif, which copied its EXIF into a HEIF Exif
 * item. Nothing in any of them is real.
 *
 * The planted values (GPS 40°42'46.08"N 74°0'21.6"W, "Fixture Person",
 * camera "TestCam") are what the tests look for afterwards.
 */
final class EvidenceFixtures
{
    public const PERSON = 'Fixture Person';
    public const CAMERA = 'TestCam';

    public static function dir(): string
    {
        return dirname(__DIR__) . '/fixtures/evidence';
    }

    public static function read(string $name): string
    {
        return (string) file_get_contents(self::dir() . '/' . $name);
    }

    /** Writes bytes to a temporary file and returns its path. */
    public static function write(string $bytes, string $suffix = '.bin'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'usv') . $suffix;
        file_put_contents($path, $bytes);

        return $path;
    }

    /**
     * An EXIF TIFF block: Make, Model, Orientation 6 (rotated 90°) and a GPS
     * IFD with latitude and longitude.
     */
    public static function gpsTiff(): string
    {
        $entry = static fn (int $tag, int $type, int $count, string $value): string => pack('nnN', $tag, $type, $count) . str_pad($value, 4, "\x00");
        $rational = static fn (int $numerator, int $denominator): string => pack('NN', $numerator, $denominator);

        // Offsets: IFD0 at 8 (2 + 4*12 + 4 = 54 bytes, to 62), Make at 62,
        // Model at 70, GPS IFD at 78 (54 bytes, to 132), latitude at 132,
        // longitude at 156.
        $ifd0 = pack('n', 4)
            . $entry(0x010F, 2, 8, pack('N', 62))
            . $entry(0x0110, 2, 8, pack('N', 70))
            . $entry(0x0112, 3, 1, pack('n', 6))
            . $entry(0x8825, 4, 1, pack('N', 78))
            . pack('N', 0);
        $gps = pack('n', 4)
            . $entry(0x0001, 2, 2, "N\x00")
            . $entry(0x0002, 5, 3, pack('N', 132))
            . $entry(0x0003, 2, 2, "W\x00")
            . $entry(0x0004, 5, 3, pack('N', 156))
            . pack('N', 0);

        return "MM\x00\x2A" . pack('N', 8)
            . $ifd0
            . str_pad(self::CAMERA, 8, "\x00")
            . "Model-X\x00"
            . $gps
            . $rational(40, 1) . $rational(42, 1) . $rational(4608, 100)
            . $rational(74, 1) . $rational(0, 1) . $rational(2160, 100);
    }

    /** photo.jpg with GPS EXIF, an XMP packet naming a person, and a comment. */
    public static function jpegWithGps(): string
    {
        $jpeg = self::read('photo.jpg');
        $exif = "Exif\x00\x00" . self::gpsTiff();
        $xmp = "http://ns.adobe.com/xap/1.0/\x00<x:xmpmeta xmlns:x=\"adobe:ns:meta/\"><rdf:RDF><rdf:Description><dc:creator>" . self::PERSON . "</dc:creator></rdf:Description></rdf:RDF></x:xmpmeta>";
        $comment = 'Taken by ' . self::PERSON;

        $segment = static fn (int $marker, string $payload): string => "\xFF" . chr($marker) . pack('n', strlen($payload) + 2) . $payload;

        return "\xFF\xD8"
            . $segment(0xE1, $exif)
            . $segment(0xE1, $xmp)
            . $segment(0xFE, $comment)
            . substr($jpeg, 2);
    }

    /** png.png with tEXt, eXIf (GPS) and tIME chunks, and bytes after IEND. */
    public static function pngWithMetadata(): string
    {
        $png = self::read('png.png');
        $chunk = static fn (string $type, string $data): string => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
        $iend = strrpos($png, 'IEND') - 4;

        return substr($png, 0, $iend)
            . $chunk('tEXt', "Author\x00" . self::PERSON)
            . $chunk('eXIf', self::gpsTiff())
            . $chunk('tIME', pack('nCCCCC', 2026, 3, 1, 22, 15, 0))
            . substr($png, $iend)
            . 'TRAILING ' . self::PERSON;
    }

    /** A one-page PDF whose information dictionary and XMP name a person. */
    public static function pdfWithAuthor(): string
    {
        $xmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?><x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF><rdf:Description><dc:creator>' . self::PERSON . '</dc:creator></rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
        $content = '0 0 1 rg 20 20 160 160 re f';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] /Contents 4 0 R >>',
            '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream",
            '<< /Type /Metadata /Subtype /XML /Length ' . strlen($xmp) . " >>\nstream\n" . $xmp . "\nendstream",
            '<< /Author (' . self::PERSON . ') /Creator (Writer \\(v2\\) for ' . self::PERSON . ') /Producer <FEFF00460069> /CreationDate (D:20260301221500Z) >>',
        ];

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $index => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1) . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf . "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R /Info 6 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }

    /**
     * A HEIC container shaped like an iPhone photo's: ftyp, a meta box with
     * item info and locations for an image item and an Exif item, and mdat
     * holding both. Not a decodable image (the image item is placeholder
     * bytes); it is for checking the Exif item is found and cleared.
     *
     * @return array{bytes: string, exif_offset: int, exif_length: int, image_offset: int, image_length: int}
     */
    public static function heicWithExif(): array
    {
        $box = static fn (string $type, string $payload): string => pack('N', strlen($payload) + 8) . $type . $payload;
        $full = static fn (string $type, int $version, string $payload): string => $box($type, chr($version) . "\x00\x00\x00" . $payload);

        $image = str_repeat("\x26\x01\xAF", 40);
        $exif = pack('N', 6) . "Exif\x00\x00" . self::gpsTiff();

        $build = static function (int $imageOffset, int $exifOffset) use ($box, $full, $image, $exif): string {
            $ftyp = $box('ftyp', 'heic' . pack('N', 0) . 'mif1heic');
            $hdlr = $full('hdlr', 0, pack('N', 0) . 'pict' . str_repeat("\x00", 12) . "\x00");
            $pitm = $full('pitm', 0, pack('n', 1));
            $iinf = $full('iinf', 0, pack('n', 2)
                . $full('infe', 2, pack('nn', 1, 0) . 'hvc1' . "\x00")
                . $full('infe', 2, pack('nn', 2, 0) . 'Exif' . "\x00"));
            $iloc = $full('iloc', 1, "\x44\x00" . pack('n', 2)
                . pack('nnn', 1, 0, 0) . pack('n', 1) . pack('NN', $imageOffset, strlen($image))
                . pack('nnn', 2, 0, 0) . pack('n', 1) . pack('NN', $exifOffset, strlen($exif)));
            $meta = $full('meta', 0, $hdlr . $pitm . $iinf . $iloc);

            return $ftyp . $meta . $box('mdat', $image . $exif);
        };

        $draft = $build(0, 0);
        $imageOffset = strlen($draft) - strlen($image) - strlen($exif);
        $exifOffset = $imageOffset + strlen($image);

        return [
            'bytes' => $build($imageOffset, $exifOffset),
            'exif_offset' => $exifOffset,
            'exif_length' => strlen($exif),
            'image_offset' => $imageOffset,
            'image_length' => strlen($image),
        ];
    }

    /** What exif_read_data() finds in a JPEG, or [] when it finds no EXIF. */
    public static function exif(string $jpeg): array
    {
        $path = self::write($jpeg, '.jpg');

        try {
            $data = @exif_read_data($path, null, true);
        } finally {
            @unlink($path);
        }

        return is_array($data) ? $data : [];
    }
}
