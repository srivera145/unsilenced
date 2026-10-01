<?php

namespace Keel\App\Support;

/**
 * Finds phrases in free text that look like a person's full name.
 *
 * Unsilenced never displays the names of accused individuals. Admins write
 * accountability summaries in their own words, and this is the backstop: a
 * summary that trips it cannot be saved until the admin confirms the flagged
 * phrases are not names of people.
 *
 * It is a heuristic and is tuned to over-report. A run of two or more
 * capitalised words is a candidate once institution words, months, Greek
 * letters and the school's own name are removed, so "Chi Phi" or "Ithaca
 * Tompkins" may be flagged and confirmed. Missing a real name is the failure
 * that matters; an extra confirmation click is not.
 */
class NameDetector
{
    /** One capitalised word: Smith, O'Neil, McDonald, Mary-Kate, Núñez. */
    private const WORD = "\p{Lu}[\p{Ll}\p{Lu}'’\-]*\p{Ll}[\p{Ll}'’\-]*";

    /** A middle initial: "J." */
    private const INITIAL = '\p{Lu}\.';

    /**
     * Words that, directly before a capitalised word, make it a person: "Dean
     * Smith". Some are also in the ignore list ("dean", "president"), so this
     * pass is what catches them.
     */
    private const TITLES = [
        'mr', 'mrs', 'ms', 'mx', 'dr', 'prof', 'professor', 'coach', 'officer', 'sgt', 'sergeant', 'detective',
        'rev', 'dean', 'president', 'provost', 'chancellor', 'judge', 'justice', 'senator', 'rep', 'representative',
    ];

    /**
     * @param list<string> $extraIgnoreWords Words to treat as not-a-name for this
     *        text, e.g. the words of the school's own name.
     * @return list<string> Distinct flagged phrases in order of appearance.
     */
    public static function find(string $text, array $extraIgnoreWords = []): array
    {
        $ignore = [];
        foreach (array_merge((array) Config::get('name_detector.ignore_words', []), $extraIgnoreWords) as $word) {
            $ignore[mb_strtolower(trim((string) $word))] = true;
        }

        $found = [];

        // "Dr. Smith", "Coach Jones": a title in front of one capitalised word
        // is a name even without a second word.
        $titles = implode('|', self::TITLES);
        if (preg_match_all('/\b(?i:' . $titles . ')\.?\s+' . self::WORD . '(?:\s+' . self::WORD . ')?/u', $text, $matches)) {
            foreach ($matches[0] as $match) {
                $found[] = trim($match);
            }
        }

        // A middle initial between two capitalised words is a person whatever
        // the words are: "Jane Q. Public", "James T. Dean".
        if (preg_match_all('/' . self::WORD . '(?:\s+' . self::INITIAL . ')+\s+' . self::WORD . '/u', $text, $initialled)) {
            foreach ($initialled[0] as $match) {
                $found[] = trim($match);
            }
        }

        // Runs of capitalised words (with optional middle initials), split
        // wherever an ignored word sits, so "Attorney General Jane Doe" still
        // yields "Jane Doe".
        $token = '(?:' . self::WORD . '|' . self::INITIAL . ')';
        if (preg_match_all('/' . $token . '(?:\s+' . $token . ')+/u', $text, $runs)) {
            foreach ($runs[0] as $run) {
                foreach (self::splitOnIgnored($run, $ignore) as $candidate) {
                    $found[] = $candidate;
                }
            }
        }

        return array_values(array_unique($found));
    }

    /** @return list<string> */
    private static function splitOnIgnored(string $run, array $ignore): array
    {
        $words = preg_split('/\s+/u', trim($run)) ?: [];
        $candidates = [];
        $current = [];

        $flush = static function () use (&$current, &$candidates): void {
            $realWords = array_filter($current, static fn (string $w): bool => !preg_match('/^\p{Lu}\.$/u', $w));
            if (count($realWords) >= 2) {
                $candidates[] = implode(' ', $current);
            }
            $current = [];
        };

        foreach ($words as $word) {
            $bare = mb_strtolower(preg_replace("/(['’]s)$/u", '', $word) ?? $word);

            if (isset($ignore[$bare]) || isset($ignore[rtrim($bare, '.')])) {
                $flush();
                continue;
            }

            $current[] = $word;
        }

        $flush();

        return $candidates;
    }
}
