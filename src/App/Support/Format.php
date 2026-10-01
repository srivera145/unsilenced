<?php

namespace Keel\App\Support;

/**
 * Small, pure formatting helpers shared by views and importers.
 */
class Format
{
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public static function number(?int $value, string $missing = '—'): string
    {
        return $value === null ? $missing : number_format($value);
    }

    /** A rate per 1,000 students: two decimals below 10, one above. */
    public static function rate(?float $value, string $missing = '—'): string
    {
        if ($value === null) {
            return $missing;
        }

        return number_format($value, $value < 10 ? 2 : 1);
    }

    public static function date(?string $date, string $format = 'M j, Y'): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        $timestamp = strtotime($date);

        return $timestamp === false ? $date : date($format, $timestamp);
    }

    public static function slug(string $text): string
    {
        $ascii = $text;
        if (class_exists(\Transliterator::class)) {
            $converted = \Transliterator::create('Any-Latin; Latin-ASCII')?->transliterate($text);
            if (is_string($converted) && $converted !== '') {
                $ascii = $converted;
            }
        } elseif (function_exists('iconv')) {
            // glibc gives "Universite"; Windows iconv gives "Universit'e". Drop the
            // accent marks some iconv builds leave behind before slugging.
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if (is_string($converted) && $converted !== '') {
                $ascii = str_replace(['"', '`', '^', '~', '?'], '', $converted);
            }
        }

        // Apostrophes join rather than split, so "St. John's" is st-johns
        // whichever branch ran above (the CLI and web PHP builds differ).
        $ascii = str_replace(["'", '’'], '', $ascii);
        $ascii = str_replace(['&', '@'], [' and ', ' at '], $ascii);
        $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $ascii), '-'));

        return $slug !== '' ? substr($slug, 0, 150) : 'school';
    }

    /** "1 school" / "3 schools". */
    public static function plural(int $count, string $singular, ?string $plural = null): string
    {
        return number_format($count) . ' ' . ($count === 1 ? $singular : ($plural ?? $singular . 's'));
    }

    /** "2019, 2020 and 2022". */
    public static function list(array $items): string
    {
        $items = array_values(array_map('strval', $items));

        if (count($items) <= 1) {
            return $items[0] ?? '';
        }

        $last = array_pop($items);

        return implode(', ', $items) . ' and ' . $last;
    }
}
