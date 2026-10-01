<?php

namespace Keel\App\Services\Survivor;

/**
 * Reads ISO base media file boxes: the container HEIC photos and M4A audio
 * share. A box is a 4-byte big-endian size (1 = a 64-bit size follows the
 * type, 0 = to the end), a 4-character type, then its payload.
 */
final class Isobmff
{
    /** Boxes that hold other boxes and nothing else. */
    public const CONTAINERS = ['moov', 'trak', 'mdia', 'minf', 'stbl', 'dinf', 'edts', 'udta', 'mvex', 'moof', 'traf', 'iprp', 'ipco'];

    /**
     * The boxes between $start and $end, in order.
     *
     * @return list<array{type: string, start: int, body: int, payload: int, end: int}>
     *         start is the box's first byte, body the first byte after its
     *         size and type, payload its first payload byte (after a uuid
     *         box's 16-byte user type), end the byte after it.
     */
    public static function boxes(string $data, int $start = 0, ?int $end = null): array
    {
        $end ??= strlen($data);
        $boxes = [];
        $position = $start;

        while ($position + 8 <= $end) {
            $size = self::uint($data, $position, 4);
            $type = substr($data, $position + 4, 4);
            $header = 8;

            if ($size === 1) {
                if ($position + 16 > $end) {
                    break;
                }
                $size = self::uint($data, $position + 8, 8);
                $header = 16;
            } elseif ($size === 0) {
                $size = $end - $position;
            }

            if ($size < $header || $position + $size > $end) {
                throw new \UnexpectedValueException('Box runs past its container.');
            }

            $body = $position + $header;
            if ($type === 'uuid') {
                $header += 16;
            }

            $boxes[] = ['type' => $type, 'start' => $position, 'body' => $body, 'payload' => $position + $header, 'end' => $position + $size];
            $position += $size;
        }

        return $boxes;
    }

    /** The first box of $type among $boxes, or null. */
    public static function find(array $boxes, string $type): ?array
    {
        foreach ($boxes as $box) {
            if ($box['type'] === $type) {
                return $box;
            }
        }

        return null;
    }

    /** The ftyp box's major brand and compatible brands, or null when the file does not start with one. */
    public static function brands(string $data): ?array
    {
        if (strlen($data) < 16 || substr($data, 4, 4) !== 'ftyp') {
            return null;
        }

        $size = self::uint($data, 0, 4);
        $size = min(max($size, 16), strlen($data));
        $compatible = [];
        for ($offset = 16; $offset + 4 <= $size; $offset += 4) {
            $compatible[] = substr($data, $offset, 4);
        }

        return ['major' => substr($data, 8, 4), 'compatible' => $compatible];
    }

    /**
     * Handler types of every track (moov/trak/mdia/hdlr): 'soun' for audio,
     * 'vide' for video, and so on.
     *
     * @return list<string>
     */
    public static function trackHandlers(string $data): array
    {
        $moov = self::find(self::boxes($data), 'moov');
        if ($moov === null) {
            return [];
        }

        $handlers = [];
        foreach (self::boxes($data, $moov['payload'], $moov['end']) as $trak) {
            if ($trak['type'] !== 'trak') {
                continue;
            }
            $mdia = self::find(self::boxes($data, $trak['payload'], $trak['end']), 'mdia');
            $hdlr = $mdia !== null ? self::find(self::boxes($data, $mdia['payload'], $mdia['end']), 'hdlr') : null;
            if ($hdlr !== null && $hdlr['payload'] + 12 <= $hdlr['end']) {
                // FullBox (4) + pre_defined (4), then handler_type.
                $handlers[] = substr($data, $hdlr['payload'] + 8, 4);
            }
        }

        return $handlers;
    }

    public static function uint(string $data, int $offset, int $bytes): int
    {
        if ($bytes === 0) {
            return 0;
        }

        if ($offset < 0 || $offset + $bytes > strlen($data)) {
            throw new \UnexpectedValueException('Read past the end of the file.');
        }

        $value = 0;
        for ($i = 0; $i < $bytes; $i++) {
            $value = ($value << 8) | ord($data[$offset + $i]);
        }

        return $value;
    }
}
