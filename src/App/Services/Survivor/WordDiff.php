<?php

namespace Keel\App\Services\Survivor;

/**
 * The side-by-side comparison on the admin review page: their original on the
 * left with removed words struck through, the published version on the right
 * with placeholders marked. Word level, by longest common subsequence.
 * Accounts are at most 5,000 characters, so the table stays small.
 */
final class WordDiff
{
    /**
     * @return array{left: string, right: string, removed: int, inserted: int}
     *         left and right are HTML, already escaped
     */
    public static function sideBySide(string $original, string $published): array
    {
        $a = self::tokens($original);
        $b = self::tokens($published);
        $ops = self::diff($a, $b);

        $left = '';
        $right = '';
        $removed = 0;
        $inserted = 0;

        foreach ($ops as [$op, $token]) {
            // The token's trailing space stays outside the mark.
            $word = rtrim($token);
            $space = htmlspecialchars(substr($token, strlen($word)), ENT_QUOTES, 'UTF-8');
            $escaped = htmlspecialchars($word, ENT_QUOTES, 'UTF-8');

            if ($op === '=') {
                $left .= $escaped . $space;
                $right .= $escaped . $space;
            } elseif ($op === '-') {
                $left .= ($word === '' ? '' : '<del class="diff-del">' . $escaped . '</del>') . $space;
                $removed += $word === '' ? 0 : 1;
            } else {
                $right .= ($word === '' ? '' : '<ins class="diff-ins">' . $escaped . '</ins>') . $space;
                $inserted += $word === '' ? 0 : 1;
            }
        }

        return ['left' => nl2br($left, false), 'right' => nl2br($right, false), 'removed' => $removed, 'inserted' => $inserted];
    }

    /** Words and single punctuation marks, each with the whitespace after it; placeholders stay whole. */
    private static function tokens(string $text): array
    {
        $placeholders = array_map(static fn (string $p): string => preg_quote($p, '/'), RedactionCheck::placeholders());
        $placeholderPattern = $placeholders === [] ? '' : '(?i:' . implode('|', $placeholders) . ')|';
        preg_match_all('/^\s+|(?:' . $placeholderPattern . '[\p{L}\p{N}]+(?:[\'’][\p{L}]+)*|[^\p{L}\p{N}\s])\s*/su', str_replace("\r\n", "\n", $text), $matches);

        return $matches[0];
    }

    /** @return list<array{0: string, 1: string}> ['=', token], ['-', token] or ['+', token] */
    private static function diff(array $a, array $b): array
    {
        // The same opening and ending need no table.
        $prefix = [];
        while ($a !== [] && $b !== [] && $a[0] === $b[0]) {
            $prefix[] = ['=', array_shift($a)];
            array_shift($b);
        }
        $suffix = [];
        while ($a !== [] && $b !== [] && end($a) === end($b)) {
            array_unshift($suffix, ['=', array_pop($a)]);
            array_pop($b);
        }

        $n = count($a);
        $m = count($b);

        // A 5,000-character account is about 1,000 tokens. Something far past
        // that is shown as removed-then-added rather than building a table
        // that could run out of memory.
        if ($n * $m > 1500000) {
            return [...$prefix, ...array_map(static fn (string $t): array => ['-', $t], $a), ...array_map(static fn (string $t): array => ['+', $t], $b), ...$suffix];
        }

        return [...$prefix, ...self::lcs($a, $b, $n, $m), ...$suffix];
    }

    private static function lcs(array $a, array $b, int $n, int $m): array
    {
        $lengths = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));

        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $lengths[$i][$j] = $a[$i] === $b[$j]
                    ? $lengths[$i + 1][$j + 1] + 1
                    : max($lengths[$i + 1][$j], $lengths[$i][$j + 1]);
            }
        }

        $ops = [];
        $i = 0;
        $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $ops[] = ['=', $a[$i]];
                $i++;
                $j++;
            } elseif ($lengths[$i + 1][$j] >= $lengths[$i][$j + 1]) {
                $ops[] = ['-', $a[$i++]];
            } else {
                $ops[] = ['+', $b[$j++]];
            }
        }
        while ($i < $n) {
            $ops[] = ['-', $a[$i++]];
        }
        while ($j < $m) {
            $ops[] = ['+', $b[$j++]];
        }

        return $ops;
    }
}
