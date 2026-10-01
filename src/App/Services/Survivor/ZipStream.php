<?php

namespace Keel\App\Services\Survivor;

/**
 * Writes a ZIP archive straight to the response as it is built, so the
 * decrypted evidence in a share link's "download all" never sits on the
 * server's disk. Files are stored, not compressed (photos and audio are
 * compressed already), and each one's CRC and sizes follow its data in a data
 * descriptor, because they are known only after the file has streamed.
 * Under 4 GB in total (20 files of 20 MB), so no ZIP64.
 */
final class ZipStream
{
    /** @var callable(string): void */
    private $out;
    private int $offset = 0;
    private array $entries = [];
    private array $names = [];

    /** @param callable(string): void $out receives the archive's bytes in order */
    public function __construct(callable $out)
    {
        $this->out = $out;
    }

    /**
     * Adds one file. $produce is called with a writer and must pass the file's
     * bytes to it, in as many pieces as it likes.
     *
     * @param callable(callable(string): void): void $produce
     */
    public function addFile(string $name, int $modifiedAt, callable $produce): void
    {
        $name = $this->uniqueName($name);
        [$dosTime, $dosDate] = self::dosTime($modifiedAt);
        $flags = 0x0008 | 0x0800; // data descriptor follows; name is UTF-8
        $headerOffset = $this->offset;

        $this->emit(pack('VvvvvvVVVvv', 0x04034B50, 20, $flags, 0, $dosTime, $dosDate, 0, 0, 0, strlen($name), 0) . $name);

        $crc = hash_init('crc32b');
        $size = 0;
        $produce(function (string $chunk) use ($crc, &$size): void {
            hash_update($crc, $chunk);
            $size += strlen($chunk);
            $this->emit($chunk);
        });
        $crc32 = (int) hexdec(hash_final($crc));

        $this->emit(pack('VVVV', 0x08074B50, $crc32, $size, $size));

        $this->entries[] = compact('name', 'flags', 'dosTime', 'dosDate', 'crc32', 'size', 'headerOffset');
    }

    public function addString(string $name, string $contents, int $modifiedAt): void
    {
        $this->addFile($name, $modifiedAt, static function (callable $write) use ($contents): void {
            $write($contents);
        });
    }

    /** Writes the central directory. Call once, last. */
    public function finish(): void
    {
        $directoryOffset = $this->offset;
        $directory = '';

        foreach ($this->entries as $entry) {
            $directory .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014B50,
                20,
                20,
                $entry['flags'],
                0,
                $entry['dosTime'],
                $entry['dosDate'],
                $entry['crc32'],
                $entry['size'],
                $entry['size'],
                strlen($entry['name']),
                0,
                0,
                0,
                0,
                0,
                $entry['headerOffset']
            ) . $entry['name'];
        }

        $this->emit($directory);
        $this->emit(pack('VvvvvVVv', 0x06054B50, 0, 0, count($this->entries), count($this->entries), strlen($directory), $directoryOffset, 0));
    }

    /**
     * A name safe inside an archive on any system: no folders, no characters
     * Windows refuses, and "photo (2).jpg" when a name repeats.
     */
    private function uniqueName(string $name): string
    {
        $name = (string) preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u', '_', $name);
        $name = trim($name, ". \t");
        if ($name === '') {
            $name = 'file';
        }

        $candidate = $name;
        $counter = 2;
        while (isset($this->names[strtolower($candidate)])) {
            $dot = strrpos($name, '.');
            $candidate = $dot === false || $dot === 0
                ? $name . ' (' . $counter . ')'
                : substr($name, 0, $dot) . ' (' . $counter . ')' . substr($name, $dot);
            $counter++;
        }

        $this->names[strtolower($candidate)] = true;

        return $candidate;
    }

    private function emit(string $bytes): void
    {
        $this->offset += strlen($bytes);
        ($this->out)($bytes);
    }

    /** @return array{0: int, 1: int} MS-DOS time and date, in UTC */
    private static function dosTime(int $timestamp): array
    {
        $parts = getdate($timestamp - (int) date('Z', $timestamp));
        $year = max(1980, $parts['year']);

        return [
            ($parts['hours'] << 11) | ($parts['minutes'] << 5) | intdiv($parts['seconds'], 2),
            (($year - 1980) << 9) | ($parts['mon'] << 5) | $parts['mday'],
        ];
    }
}
