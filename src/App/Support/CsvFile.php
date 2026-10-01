<?php

namespace Keel\App\Support;

use RuntimeException;

/**
 * A CSV file read one row at a time, keyed by header.
 *
 * Federal data files are not consistent about encoding: IPEDS publishes
 * Windows-1252, other exports are UTF-8 with or without a BOM. Each line that is
 * not valid UTF-8 is converted from Windows-1252, so a name like "Université"
 * survives either way.
 *
 * Header lookups are by name, case-insensitive and trimmed, never by position.
 */
class CsvFile
{
    /** @var resource */
    private $handle;

    /** @var list<string> Headers exactly as they appear in the file (BOM and whitespace removed). */
    private array $headers;

    /** @var array<string, int> Lowercased header => column index. */
    private array $index = [];

    private int $line = 0;

    public function __construct(private readonly string $path)
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException("File not found or not readable: {$path}");
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Could not open {$path}");
        }

        $this->handle = $handle;
        $header = $this->readRow();

        if ($header === null || $header === [] || $header === [null]) {
            throw new RuntimeException("{$path} is empty: no header row.");
        }

        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $this->headers = array_map(static fn ($value): string => trim((string) $value), $header);

        foreach ($this->headers as $position => $name) {
            $key = strtolower($name);
            if ($key !== '' && !isset($this->index[$key])) {
                $this->index[$key] = $position;
            }
        }
    }

    public function __destruct()
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }

    /** @return list<string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function has(string $header): bool
    {
        return isset($this->index[strtolower(trim($header))]);
    }

    /** The current data row's line number in the file (the header is line 1). */
    public function line(): int
    {
        return $this->line;
    }

    /**
     * Yields each data row as [lowercased header => trimmed value]. Blank lines
     * are skipped.
     *
     * @return \Generator<int, array<string, string>>
     */
    public function rows(): \Generator
    {
        while (($row = $this->readRow()) !== null) {
            if ($row === [null] || $row === []) {
                continue;
            }

            $assoc = [];
            foreach ($this->index as $key => $position) {
                $assoc[$key] = trim((string) ($row[$position] ?? ''));
            }

            yield $this->line => $assoc;
        }
    }

    /** Value of a header in a row from rows(), or '' when the header is absent. */
    public static function value(array $row, string $header): string
    {
        return $row[strtolower(trim($header))] ?? '';
    }

    private function readRow(): ?array
    {
        $row = fgetcsv($this->handle, null, ',', '"', '');
        if ($row === false) {
            return null;
        }

        $this->line++;

        return array_map(static function ($value) {
            if ($value === null) {
                return null;
            }

            return mb_check_encoding($value, 'UTF-8')
                ? $value
                : mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }, $row);
    }
}
