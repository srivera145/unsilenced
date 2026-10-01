<?php

namespace Keel\App\Services\Survivor;

/**
 * Decides what an uploaded file is from its first bytes ("magic bytes"). The
 * file's name and the type the browser claims are never trusted: a renamed
 * file is judged by what it contains.
 *
 * Allowed: JPEG, PNG and HEIC photos, PDF, plain text, M4A and MP3 audio.
 * Everything else is refused with a message saying what to do instead. Video
 * never gets in, including an MP4 renamed .m4a: an ISO media file is accepted
 * as audio only when every track is a sound track.
 */
final class FileInspector
{
    private const HEIC_BRANDS = ['heic', 'heix', 'heim', 'heis', 'hevc', 'hevx', 'hevm', 'hevs'];
    private const GENERIC_HEIF_BRANDS = ['mif1', 'msf1'];
    private const AUDIO_BRANDS = ['M4A ', 'M4B ', 'mp42', 'mp41', 'isom', 'iso2', 'iso3', 'iso4', 'iso5', 'iso6', 'dash'];

    public const ALLOWED_SUMMARY = 'You can add photos (JPEG, PNG or HEIC), PDFs, text files and audio recordings (M4A or MP3).';

    /** @return string a key of config evidence.types */
    public static function detect(string $bytes): string
    {
        if ($bytes === '') {
            throw new EvidenceRejected('This file is empty.');
        }

        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return 'jpeg';
        }

        if (str_starts_with($bytes, "\x89PNG\r\n\x1A\n")) {
            return 'png';
        }

        if (str_starts_with($bytes, '%PDF-')) {
            return 'pdf';
        }

        $brands = Isobmff::brands($bytes);
        if ($brands !== null) {
            return self::isoMedia($bytes, $brands);
        }

        // Before the MP3 check: a UTF-16 byte-order mark (FF FE) also looks
        // like an MPEG frame sync.
        self::refuseKnownTypes($bytes);

        if (self::looksLikeMp3($bytes)) {
            return 'mp3';
        }

        if (self::isPlainText($bytes)) {
            return 'text';
        }

        throw new EvidenceRejected('This kind of file cannot be added. ' . self::ALLOWED_SUMMARY);
    }

    private static function isoMedia(string $bytes, array $brands): string
    {
        $all = array_merge([$brands['major']], $brands['compatible']);

        // A HEIC brand anywhere: an iPhone photo is mif1 or heic with the other compatible.
        if (array_intersect($all, self::HEIC_BRANDS) !== []) {
            return 'heic';
        }

        if (in_array($brands['major'], ['avif', 'avis'], true) || in_array($brands['major'], self::GENERIC_HEIF_BRANDS, true)) {
            throw new EvidenceRejected('This image format cannot be added. Save it as JPEG or PNG and try again.');
        }

        if (in_array($brands['major'], ['qt  ', 'M4V ', 'M4VH', 'M4VP', 'avc1', '3gp4', '3gp5', '3gp6', '3g2a'], true)) {
            throw new EvidenceRejected('Video cannot be added here. If a recording matters, keep it safe and give it only to the police or an attorney.');
        }

        try {
            $handlers = Isobmff::trackHandlers($bytes);
        } catch (\UnexpectedValueException) {
            throw new EvidenceRejected('This audio file looks damaged, so it cannot be added.');
        }

        if (in_array('vide', $handlers, true)) {
            throw new EvidenceRejected('Video cannot be added here. If a recording matters, keep it safe and give it only to the police or an attorney.');
        }

        // Sound, plus the chapter and metadata tracks audiobooks and podcasts carry.
        if (in_array('soun', $handlers, true)
            && array_diff($handlers, ['soun', 'text', 'meta', 'hint', 'sbtl']) === []
            && array_intersect($all, self::AUDIO_BRANDS) !== []) {
            return 'm4a';
        }

        throw new EvidenceRejected('This kind of file cannot be added. ' . self::ALLOWED_SUMMARY);
    }

    private static function looksLikeMp3(string $bytes): bool
    {
        $offset = 0;

        // One or more ID3v2 tags, then the first audio frame.
        while (substr($bytes, $offset, 3) === 'ID3' && strlen($bytes) >= $offset + 10) {
            $size = self::syncsafe(substr($bytes, $offset + 6, 4));
            $footer = (ord($bytes[$offset + 5]) & 0x10) !== 0 ? 10 : 0;
            $offset += 10 + $size + $footer;
        }

        // Some encoders pad between the tag and the first frame.
        while ($offset < strlen($bytes) && $bytes[$offset] === "\x00" && $offset < 65536) {
            $offset++;
        }

        if (strlen($bytes) < $offset + 4) {
            return false;
        }

        $b1 = ord($bytes[$offset]);
        $b2 = ord($bytes[$offset + 1]);
        $b3 = ord($bytes[$offset + 2]);

        $sync = $b1 === 0xFF && ($b2 & 0xE0) === 0xE0;
        $version = ($b2 >> 3) & 0x03;   // 01 is reserved
        $layer = ($b2 >> 1) & 0x03;     // 00 is reserved (and is AAC's ADTS)
        $bitrate = ($b3 >> 4) & 0x0F;   // 1111 is invalid
        $rate = ($b3 >> 2) & 0x03;      // 11 is reserved

        return $sync && $version !== 0x01 && $layer !== 0x00 && $bitrate !== 0x0F && $rate !== 0x03;
    }

    /** Types people often try, refused with advice rather than "not allowed". */
    private static function refuseKnownTypes(string $bytes): void
    {
        $known = [
            'GIF8' => 'Animated and GIF images cannot be added. Take a screenshot, or save it as PNG.',
            "PK\x03\x04" => 'Documents and zip files cannot be added. Save a document as PDF, or take screenshots.',
            "\xD0\xCF\x11\xE0" => 'Documents cannot be added in this format. Save it as PDF.',
            'RIFF' => 'This file type cannot be added. Save images as PNG or JPEG, and audio as MP3 or M4A.',
            'OggS' => 'This audio format cannot be added. Save it as MP3 or M4A.',
            'fLaC' => 'This audio format cannot be added. Save it as MP3 or M4A.',
            "\x1A\x45\xDF\xA3" => 'Video cannot be added here. If a recording matters, keep it safe and give it only to the police or an attorney.',
            'Rar!' => 'Zip and archive files cannot be added. Add each file on its own.',
            "7z\xBC\xAF" => 'Zip and archive files cannot be added. Add each file on its own.',
            "\xFF\xFE" => 'This text file is saved as UTF-16. Save it as UTF-8 text and try again.',
            "\xFE\xFF" => 'This text file is saved as UTF-16. Save it as UTF-8 text and try again.',
        ];

        foreach ($known as $magic => $message) {
            if (str_starts_with($bytes, $magic)) {
                throw new EvidenceRejected($message);
            }
        }
    }

    /**
     * UTF-8 (with or without a BOM), no NUL bytes, and no control characters
     * other than tab, line feed, carriage return and form feed.
     */
    private static function isPlainText(string $bytes): bool
    {
        if (str_contains($bytes, "\x00") || !mb_check_encoding($bytes, 'UTF-8')) {
            return false;
        }

        return preg_match('/[\x01-\x08\x0B\x0E-\x1F\x7F]/', $bytes) !== 1;
    }

    public static function syncsafe(string $four): int
    {
        if (strlen($four) !== 4) {
            return 0;
        }

        return ((ord($four[0]) & 0x7F) << 21) | ((ord($four[1]) & 0x7F) << 14) | ((ord($four[2]) & 0x7F) << 7) | (ord($four[3]) & 0x7F);
    }
}
