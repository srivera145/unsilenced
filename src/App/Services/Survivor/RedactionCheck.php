<?php

namespace Keel\App\Services\Survivor;

use Keel\App\Support\Config;

/**
 * Enforces "admins redact, they never add": the published version of an
 * account may only remove words from their original, in order, and put one of
 * the configured placeholders ("[name removed]") where something was taken
 * out. Punctuation, capitals and spacing may change; words may not.
 *
 * It cannot tell whether removing a word changes the meaning ("did not" to
 * "did"); the side-by-side diff on the review page is there for that.
 */
final class RedactionCheck
{
    /** @return list<string> */
    public static function placeholders(): array
    {
        return array_values((array) Config::get('survivor_reports.redaction_placeholders', []));
    }

    /**
     * @return array{ok: bool, added: list<string>, unknown_brackets: list<string>}
     *         added: words in the published version that their original does
     *         not have at that point; unknown_brackets: bracketed text that is
     *         not a placeholder.
     */
    public static function check(string $original, string $published): array
    {
        $withoutPlaceholders = str_ireplace(self::placeholders(), ' ', $published);

        preg_match_all('/\[[^\]]*\]/u', $withoutPlaceholders, $brackets);
        $unknownBrackets = array_values(array_unique($brackets[0]));
        $withoutPlaceholders = (string) preg_replace('/\[[^\]]*\]/u', ' ', $withoutPlaceholders);

        $source = self::words($original);
        $added = [];
        $cursor = 0;
        $sourceCount = count($source);

        // Each published word must appear in their original after the previous
        // one. Matching at the earliest place is enough to decide that. A word
        // that cannot be matched is reported and the search carries on from
        // where it was, so one added word does not flag everything after it.
        foreach (self::words($withoutPlaceholders) as $word) {
            $at = $cursor;
            while ($at < $sourceCount && $source[$at] !== $word) {
                $at++;
            }

            if ($at >= $sourceCount) {
                $added[] = $word;
                continue;
            }

            $cursor = $at + 1;
        }

        return [
            'ok' => $added === [] && $unknownBrackets === [],
            'added' => array_values(array_unique($added)),
            'unknown_brackets' => $unknownBrackets,
        ];
    }

    /** @return list<string> lower-case words and numbers, in order */
    public static function words(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}]+(?:[\'’][\p{L}]+)*/u', mb_strtolower($text, 'UTF-8'), $matches);

        return str_replace('’', "'", $matches[0]);
    }
}
