<?php

namespace Keel\App\Services\Survivor;

/**
 * Makes the copy of an evidence file that admins see, without the metadata
 * that can identify the person who made it: camera EXIF and GPS position,
 * XMP, embedded thumbnails, comments, audio tags, a PDF's author.
 *
 * The original is never changed (its metadata may matter in court); it is
 * released only through the survivor's own share links.
 *
 * Images and audio keep only what is needed to show or play them (an
 * allowlist), so metadata in a place this code has never seen is dropped, not
 * kept. Pure PHP, no GD or Imagick: the result is the same on every server,
 * lossless, and testable.
 *
 * status:
 *   stripped     nothing that identifies is left
 *   partial      best effort; some may remain (a PDF whose metadata is
 *                compressed or encrypted). The admin page says so.
 *   unavailable  the file could not be parsed; admins cannot view it.
 */
final class MetadataStripper
{
    public const STRIPPED = 'stripped';
    public const PARTIAL = 'partial';
    public const UNAVAILABLE = 'unavailable';

    /** JPEG segments kept: tables, frame and scan headers. Every APPn and COM goes, except Adobe's colour marker. */
    private const JPEG_KEEP = [0xC0, 0xC1, 0xC2, 0xC3, 0xC4, 0xC5, 0xC6, 0xC7, 0xC9, 0xCA, 0xCB, 0xCC, 0xCD, 0xCE, 0xCF, 0xDA, 0xDB, 0xDD];

    /** PNG chunks kept: image data, transparency, colour and animation. Text, eXIf, time and ICC profiles go. */
    private const PNG_KEEP = ['IHDR', 'PLTE', 'IDAT', 'IEND', 'tRNS', 'gAMA', 'cHRM', 'sRGB', 'sBIT', 'bKGD', 'acTL', 'fcTL', 'fdAT'];

    /** @return array{bytes: ?string, status: string, orientation: ?int} */
    public static function strip(string $kind, string $bytes): array
    {
        try {
            return match ($kind) {
                'jpeg' => self::jpeg($bytes),
                'png' => self::png($bytes),
                'heic' => self::heic($bytes),
                'm4a' => self::m4a($bytes),
                'mp3' => self::mp3($bytes),
                'pdf' => self::pdf($bytes),
                // Plain text has nowhere to keep metadata.
                'text' => self::result($bytes, self::STRIPPED),
                default => throw new \InvalidArgumentException('Unknown evidence kind.'),
            };
        } catch (\UnexpectedValueException) {
            return ['bytes' => null, 'status' => self::UNAVAILABLE, 'orientation' => null];
        }
    }

    // --- JPEG ----------------------------------------------------------------

    private static function jpeg(string $data): array
    {
        $length = strlen($data);
        if ($length < 4 || !str_starts_with($data, "\xFF\xD8")) {
            throw new \UnexpectedValueException('Not a JPEG.');
        }

        $out = "\xFF\xD8";
        $orientation = null;
        $position = 2;

        while ($position < $length) {
            if ($data[$position] !== "\xFF") {
                throw new \UnexpectedValueException('Expected a JPEG marker.');
            }

            // Fill bytes: any number of 0xFF before a marker.
            while ($position + 1 < $length && $data[$position + 1] === "\xFF") {
                $position++;
            }
            if ($position + 1 >= $length) {
                break;
            }

            $marker = ord($data[$position + 1]);

            if ($marker === 0xD9) {
                break; // EOI. Anything after it (appended images, trailers) is dropped.
            }

            if (($marker >= 0xD0 && $marker <= 0xD7) || $marker === 0x01) {
                $out .= substr($data, $position, 2);
                $position += 2;
                continue;
            }

            $segmentLength = Isobmff::uint($data, $position + 2, 2);
            if ($segmentLength < 2 || $position + 2 + $segmentLength > $length) {
                throw new \UnexpectedValueException('JPEG segment runs past the end.');
            }

            $payload = substr($data, $position + 4, $segmentLength - 2);
            if ($marker === 0xE1 && str_starts_with($payload, "Exif\x00\x00")) {
                $orientation ??= self::exifOrientation(substr($payload, 6));
            }

            $keep = in_array($marker, self::JPEG_KEEP, true)
                || ($marker === 0xEE && str_starts_with($payload, 'Adobe'));

            if ($keep) {
                $out .= substr($data, $position, 2 + $segmentLength);
            }

            $position += 2 + $segmentLength;

            if ($marker === 0xDA) {
                $scanEnd = self::jpegScanEnd($data, $position);
                $out .= substr($data, $position, $scanEnd - $position);
                $position = $scanEnd;
            }
        }

        return self::result($out . "\xFF\xD9", self::STRIPPED, $orientation);
    }

    /** Where the entropy-coded data after a scan header ends: the next real marker. */
    private static function jpegScanEnd(string $data, int $from): int
    {
        $length = strlen($data);
        $next = $from;

        while (true) {
            $ff = strpos($data, "\xFF", $next);
            if ($ff === false || $ff + 1 >= $length) {
                return $length; // a truncated file: keep what there is
            }

            $byte = ord($data[$ff + 1]);
            if ($byte === 0x00 || ($byte >= 0xD0 && $byte <= 0xD7)) {
                $next = $ff + 2; // stuffed 0xFF, or a restart marker inside the scan
                continue;
            }
            if ($byte === 0xFF) {
                $next = $ff + 1;
                continue;
            }

            return $ff;
        }
    }

    /** The Orientation tag (1-8) from an EXIF TIFF block, or null. Not identifying; used to show the photo upright. */
    public static function exifOrientation(string $tiff): ?int
    {
        $length = strlen($tiff);
        if ($length < 8) {
            return null;
        }

        $order = substr($tiff, 0, 2);
        if ($order !== 'II' && $order !== 'MM') {
            return null;
        }

        $u16 = static fn (int $at): int => unpack($order === 'II' ? 'v' : 'n', substr($tiff, $at, 2))[1];
        $u32 = static fn (int $at): int => unpack($order === 'II' ? 'V' : 'N', substr($tiff, $at, 4))[1];

        $ifd = $u32(4);
        if ($ifd + 2 > $length) {
            return null;
        }

        $count = $u16($ifd);
        for ($i = 0; $i < $count; $i++) {
            $entry = $ifd + 2 + $i * 12;
            if ($entry + 12 > $length) {
                break;
            }
            if ($u16($entry) === 0x0112) {
                $value = $u16($entry + 8);

                return $value >= 1 && $value <= 8 ? $value : null;
            }
        }

        return null;
    }

    // --- PNG -----------------------------------------------------------------

    private static function png(string $data): array
    {
        $length = strlen($data);
        $out = substr($data, 0, 8);
        $position = 8;
        $orientation = null;
        $ended = false;

        while ($position + 12 <= $length) {
            $chunkLength = Isobmff::uint($data, $position, 4);
            $type = substr($data, $position + 4, 4);
            if ($position + 12 + $chunkLength > $length) {
                throw new \UnexpectedValueException('PNG chunk runs past the end.');
            }

            if ($type === 'eXIf') {
                $orientation ??= self::exifOrientation(substr($data, $position + 8, $chunkLength));
            }

            if (in_array($type, self::PNG_KEEP, true)) {
                $out .= substr($data, $position, 12 + $chunkLength);
            }

            $position += 12 + $chunkLength;

            if ($type === 'IEND') {
                $ended = true;
                break; // anything after IEND is dropped
            }
        }

        if (!$ended) {
            throw new \UnexpectedValueException('PNG has no end chunk.');
        }

        return self::result($out, self::STRIPPED, $orientation);
    }

    // --- HEIC ------------------------------------------------------------------

    /**
     * HEIC keeps EXIF (with GPS) and XMP as "items": data blocks listed in the
     * meta box's item info (iinf) and located by its item location table
     * (iloc). Each such block is overwritten with zeros in place and its item
     * type renamed, so every offset in the file stays valid and readers skip
     * the item.
     */
    private static function heic(string $data): array
    {
        $meta = Isobmff::find(Isobmff::boxes($data), 'meta') ?? throw new \UnexpectedValueException('No meta box.');
        $children = Isobmff::boxes($data, $meta['payload'] + 4, $meta['end']); // meta is a FullBox
        $iinf = Isobmff::find($children, 'iinf');
        $iloc = Isobmff::find($children, 'iloc');
        $idat = Isobmff::find($children, 'idat');

        if ($iinf === null || $iloc === null) {
            throw new \UnexpectedValueException('No item tables.');
        }

        $remove = self::heicMetadataItems($data, $iinf);
        if ($remove === []) {
            return self::result($data, self::STRIPPED);
        }

        $out = $data;
        $cleared = [];

        foreach (self::heicItemExtents($data, $iloc) as [$itemId, $method, $offset, $extentLength]) {
            if (!isset($remove[$itemId])) {
                continue;
            }

            if ($method === 0) {
                $start = $offset;
            } elseif ($method === 1 && $idat !== null) {
                $start = $idat['payload'] + $offset;
            } else {
                throw new \UnexpectedValueException('Item stored in a way this code cannot clear.');
            }

            if ($extentLength === 0 || $start + $extentLength > strlen($out)) {
                throw new \UnexpectedValueException('Item extent out of range.');
            }

            $out = substr_replace($out, str_repeat("\x00", $extentLength), $start, $extentLength);
            $cleared[$itemId] = true;
        }

        foreach ($remove as $typeOffset) {
            $out = substr_replace($out, 'hide', $typeOffset, 4);
        }

        return self::result($out, count($cleared) === count($remove) ? self::STRIPPED : self::PARTIAL);
    }

    /** @return array<int, int> item id => offset of its item_type, for EXIF and XMP items */
    private static function heicMetadataItems(string $data, array $iinf): array
    {
        $version = ord($data[$iinf['payload']]);
        $first = $iinf['payload'] + 4 + ($version === 0 ? 2 : 4);
        $items = [];

        foreach (Isobmff::boxes($data, $first, $iinf['end']) as $infe) {
            if ($infe['type'] !== 'infe') {
                continue;
            }

            $infeVersion = ord($data[$infe['payload']]);
            if ($infeVersion < 2) {
                continue; // versions 0 and 1 have no item type
            }

            $position = $infe['payload'] + 4;
            $idSize = $infeVersion === 2 ? 2 : 4;
            $itemId = Isobmff::uint($data, $position, $idSize);
            $position += $idSize + 2; // item_protection_index
            $typeOffset = $position;
            $type = substr($data, $position, 4);

            $isXmp = false;
            if ($type === 'mime') {
                $nameEnd = strpos($data, "\x00", $position + 4);
                if ($nameEnd !== false && $nameEnd < $infe['end']) {
                    $typeEnd = strpos($data, "\x00", $nameEnd + 1);
                    $contentType = strtolower(substr($data, $nameEnd + 1, min($typeEnd === false ? $infe['end'] : $typeEnd, $infe['end']) - $nameEnd - 1));
                    $isXmp = str_contains($contentType, 'rdf+xml') || str_contains($contentType, 'xmp');
                }
            }

            if ($type === 'Exif' || $isXmp) {
                $items[$itemId] = $typeOffset;
            }
        }

        return $items;
    }

    /** @return list<array{0: int, 1: int, 2: int, 3: int}> item id, construction method, offset, length */
    private static function heicItemExtents(string $data, array $iloc): array
    {
        $position = $iloc['payload'];
        $version = ord($data[$position]);
        $position += 4;

        $sizes = ord($data[$position]);
        $offsetSize = $sizes >> 4;
        $lengthSize = $sizes & 0x0F;
        $sizes = ord($data[$position + 1]);
        $baseOffsetSize = $sizes >> 4;
        $indexSize = $version >= 1 ? $sizes & 0x0F : 0;
        $position += 2;

        $countSize = $version < 2 ? 2 : 4;
        $count = Isobmff::uint($data, $position, $countSize);
        $position += $countSize;

        $extents = [];
        for ($i = 0; $i < $count; $i++) {
            $itemId = Isobmff::uint($data, $position, $countSize);
            $position += $countSize;

            $method = 0;
            if ($version >= 1) {
                $method = Isobmff::uint($data, $position, 2) & 0x0F;
                $position += 2;
            }

            $position += 2; // data_reference_index
            $base = Isobmff::uint($data, $position, $baseOffsetSize);
            $position += $baseOffsetSize;
            $extentCount = Isobmff::uint($data, $position, 2);
            $position += 2;

            for ($e = 0; $e < $extentCount; $e++) {
                $position += $indexSize;
                $offset = Isobmff::uint($data, $position, $offsetSize);
                $position += $offsetSize;
                $extentLength = Isobmff::uint($data, $position, $lengthSize);
                $position += $lengthSize;
                $extents[] = [$itemId, $method, $base + $offset, $extentLength];
            }

            if ($position > $iloc['end']) {
                throw new \UnexpectedValueException('Item location table runs past its box.');
            }
        }

        return $extents;
    }

    // --- M4A -------------------------------------------------------------------

    /**
     * iTunes-style tags, user data and XMP live in udta, meta and uuid boxes.
     * Each becomes a "free" box of the same size, filled with zeros, so the
     * audio's chunk offsets stay valid. Creation and modification times in the
     * movie, track and media headers are zeroed too.
     */
    private static function m4a(string $data): array
    {
        $out = $data;
        self::neutralizeIsoBoxes($out, 0, strlen($out));

        return self::result($out, self::STRIPPED);
    }

    private static function neutralizeIsoBoxes(string &$data, int $start, int $end): void
    {
        foreach (Isobmff::boxes($data, $start, $end) as $box) {
            $type = $box['type'];

            if (in_array($type, ['udta', 'meta', 'uuid', 'ilst', 'XMP_'], true)) {
                $data = substr_replace($data, 'free', $box['start'] + 4, 4);
                $data = substr_replace($data, str_repeat("\x00", $box['end'] - $box['body']), $box['body'], $box['end'] - $box['body']);
                continue;
            }

            if (in_array($type, ['mvhd', 'tkhd', 'mdhd'], true) && $box['payload'] + 4 <= $box['end']) {
                $timesLength = ord($data[$box['payload']]) === 1 ? 16 : 8;
                if ($box['payload'] + 4 + $timesLength <= $box['end']) {
                    $data = substr_replace($data, str_repeat("\x00", $timesLength), $box['payload'] + 4, $timesLength);
                }
                continue;
            }

            if (in_array($type, ['moov', 'trak', 'mdia', 'minf', 'stbl', 'edts', 'dinf'], true)) {
                self::neutralizeIsoBoxes($data, $box['payload'], $box['end']);
            }
        }
    }

    // --- MP3 -------------------------------------------------------------------

    /** Removes ID3v2 tags at the start, and ID3v1, Enhanced TAG, APE, Lyrics3 and ID3v2 footer tags at the end. */
    private static function mp3(string $data): array
    {
        $start = 0;
        $length = strlen($data);

        while (substr($data, $start, 3) === 'ID3' && $length >= $start + 10) {
            $size = FileInspector::syncsafe(substr($data, $start + 6, 4));
            $footer = (ord($data[$start + 5]) & 0x10) !== 0 ? 10 : 0;
            $start += 10 + $size + $footer;
        }

        $end = $length;
        $changed = true;
        while ($changed && $end > $start) {
            $changed = false;

            if ($end - 128 >= $start && substr($data, $end - 128, 3) === 'TAG') {
                $end -= 128;
                $changed = true;
            }
            if ($end - 227 >= $start && substr($data, $end - 227, 4) === 'TAG+') {
                $end -= 227;
                $changed = true;
            }
            if ($end - 32 >= $start && substr($data, $end - 32, 8) === 'APETAGEX') {
                $tagSize = unpack('V', substr($data, $end - 20, 4))[1];
                $flags = unpack('V', substr($data, $end - 12, 4))[1];
                $end -= $tagSize + (($flags & 0x80000000) !== 0 ? 32 : 0);
                $changed = true;
            }
            if ($end - 15 >= $start && substr($data, $end - 9, 9) === 'LYRICS200') {
                $end -= 15 + (int) substr($data, $end - 15, 6);
                $changed = true;
            }
            if ($end - 10 >= $start && substr($data, $end - 10, 3) === '3DI') {
                $end -= 20 + FileInspector::syncsafe(substr($data, $end - 4, 4));
                $changed = true;
            }
        }

        if ($end <= $start) {
            throw new \UnexpectedValueException('No audio left after removing tags.');
        }

        return self::result(substr($data, $start, $end - $start), self::STRIPPED);
    }

    // --- PDF -------------------------------------------------------------------

    /**
     * Best effort, without rewriting the file: every string in the document
     * information dictionary (author, creator, title, dates and any custom
     * entries) is overwritten with spaces of the same length, and so is every
     * uncompressed XMP packet, so the cross-reference table stays valid.
     * Bytes are changed in place: no copy of a 20 MB file per string.
     *
     * Metadata inside compressed object streams, compressed XMP streams or an
     * encrypted PDF cannot be reached this way: the copy is then "partial" and
     * the admin page says some metadata may remain.
     */
    private static function pdf(string $data): array
    {
        $out = $data;
        $partial = false;

        if (preg_match_all('#/Info\s+(\d+)\s+(\d+)\s+R#', $data, $references, PREG_SET_ORDER)) {
            foreach ($references as $reference) {
                // An incremental update can define the object again: blank every definition.
                if (!preg_match_all('#(?<!\d)' . $reference[1] . '\s+' . $reference[2] . '\s+obj\b#', $data, $definitions, PREG_OFFSET_CAPTURE)) {
                    $partial = true; // in a compressed object stream
                    continue;
                }

                foreach ($definitions[0] as [$text, $offset]) {
                    $from = $offset + strlen($text);
                    $to = strpos($out, 'endobj', $from);
                    if ($to === false) {
                        $partial = true;
                        continue;
                    }
                    self::blankPdfStrings($out, $from, $to);
                }
            }
        }

        // An information dictionary written inline in a trailer, and the usual
        // keys wherever else they appear.
        if (preg_match_all('#/Info\s*<<#', $data, $inline, PREG_OFFSET_CAPTURE)) {
            foreach ($inline[0] as [$text, $offset]) {
                $dictionaryStart = $offset + strlen($text) - 2;
                self::blankPdfStrings($out, $dictionaryStart, self::pdfDictionaryEnd($out, $dictionaryStart));
            }
        }

        if (preg_match_all('#/(?:Author|Creator|Producer|Title|Subject|Keywords|CreationDate|ModDate|Company|SourceModified|Trapped)\s*(?=[(<](?!<))#', $data, $keys, PREG_OFFSET_CAPTURE)) {
            foreach ($keys[0] as [$text, $offset]) {
                self::blankPdfStringAt($out, $offset + strlen($text));
            }
        }

        self::blankBetween($out, '<?xpacket begin', '<?xpacket end');
        self::blankBetween($out, '<x:xmpmeta', '</x:xmpmeta>');

        if (str_contains($data, '/ObjStm') || str_contains($data, '/Encrypt')
            || preg_match('#/Type\s*/Metadata\b[^>]{0,200}/Filter|/Filter[^>]{0,200}/Type\s*/Metadata\b#', $data)) {
            $partial = true;
        }

        return self::result($out, $partial ? self::PARTIAL : self::STRIPPED);
    }

    /** Overwrites every literal and hex string between $from and $to. */
    private static function blankPdfStrings(string &$pdf, int $from, int $to): void
    {
        $position = $from;
        while ($position < $to) {
            $char = $pdf[$position];
            if ($char === '(' || ($char === '<' && ($pdf[$position + 1] ?? '') !== '<')) {
                $position = self::blankPdfStringAt($pdf, $position) + 1;
                continue;
            }
            $position += $char === '<' ? 2 : 1; // "<<" opens a nested dictionary
        }
    }

    /** Blanks the string opening at $position; returns the offset of its closing ")" or ">". */
    private static function blankPdfStringAt(string &$pdf, int $position): int
    {
        $end = self::pdfStringEnd($pdf, $position);
        $hex = $pdf[$position] === '<';

        for ($i = $position + 1; $i < $end; $i++) {
            if (!$hex) {
                $pdf[$i] = ' ';
            } elseif (ctype_xdigit($pdf[$i])) {
                $pdf[$i] = '0';
            }
        }

        return $end;
    }

    /** Offset of the ")" or ">" that closes the string opening at $position. */
    private static function pdfStringEnd(string $pdf, int $position): int
    {
        $length = strlen($pdf);

        if ($pdf[$position] === '<') {
            $end = strpos($pdf, '>', $position + 1);

            return $end === false ? $length - 1 : $end;
        }

        $depth = 0;
        for ($i = $position; $i < $length; $i++) {
            $char = $pdf[$i];
            if ($char === '\\') {
                $i++;
                continue;
            }
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return $length - 1;
    }

    private static function pdfDictionaryEnd(string $pdf, int $start): int
    {
        $depth = 0;
        $length = strlen($pdf);
        for ($i = $start; $i < $length - 1; $i++) {
            $pair = $pdf[$i] . $pdf[$i + 1];
            if ($pair === '<<') {
                $depth++;
                $i++;
            } elseif ($pair === '>>') {
                $depth--;
                $i++;
                if ($depth === 0) {
                    return $i;
                }
            } elseif ($pdf[$i] === '(' || $pdf[$i] === '<') {
                $i = self::pdfStringEnd($pdf, $i);
            }
        }

        return $length;
    }

    /** Spaces over everything from each $open to the end of the next $close (and its closing "?>" for xpacket). */
    private static function blankBetween(string &$pdf, string $open, string $close): void
    {
        $offset = 0;
        while (($start = strpos($pdf, $open, $offset)) !== false) {
            $closeAt = strpos($pdf, $close, $start + strlen($open));
            if ($closeAt === false) {
                return;
            }

            $end = $closeAt + strlen($close);
            if (str_starts_with($close, '<?')) {
                $questionEnd = strpos($pdf, '?>', $end);
                $end = $questionEnd === false ? $end : $questionEnd + 2;
            }

            for ($i = $start; $i < $end; $i++) {
                $pdf[$i] = ' ';
            }
            $offset = $end;
        }
    }

    private static function result(string $bytes, string $status, ?int $orientation = null): array
    {
        return ['bytes' => $bytes, 'status' => $status, 'orientation' => $orientation];
    }
}
