<?php

namespace Keel\App\Services\Survivor;

use Keel\App\Support\NameDetector;

/**
 * Finds what in a survivor's account could identify them or anyone else,
 * before they submit it: names, phone numbers, emails, street addresses,
 * room and apartment numbers, fraternity and sorority names, social handles.
 *
 * They see each one highlighted and either remove it or confirm. Nothing is
 * changed for them, and nothing is stored or logged by the scan. Admins redact
 * the published version separately; this is the first pass, in their hands.
 *
 * Like NameDetector, tuned to over-report: a false flag costs a click, a
 * missed name could cost far more.
 */
final class NameScanService
{
    public const LABELS = [
        'email' => 'an email address',
        'handle' => 'a social media handle or link',
        'phone' => 'a phone number',
        'address' => 'a street address',
        'room' => 'a room or apartment number',
        'greek' => 'a fraternity or sorority name',
        'role' => 'a role, title or team that could point to one person',
        'name' => 'a name',
    ];

    /**
     * Roles and titles held by one person, or a few (Phase 2.1). In a small
     * place "my RA" or "the coach" names someone as surely as a name does.
     * The words before the role are part of what is highlighted ("my RA",
     * "the head coach", "my chemistry professor"). Uppercase abbreviations
     * only: "ta" or "ra" inside ordinary words never match.
     */
    private const ROLE_DETERMINERS = 'my|his|her|their|our|your|the|a|an|one|that|this';
    private const ROLE_QUALIFIERS = 'former|old|new|assistant|associate|head|team|strength|athletic|chapter|vice|class|club|student|resident|graduate|teaching|hall|floor';
    private const ROLE_ABBREVIATIONS = '(?-i:R\.?A\.?|T\.?A\.?|R\.?D\.?)(?-i:s)?';
    private const ROLES = 'resident\s+(?:advisor|adviser|assistant|director)s?|hall\s+directors?|teaching\s+assistants?|graduate\s+assistants?'
        . '|coach(?:es)?|co-?captains?|captains?|(?:vice[\s-])?presidents?|chapter\s+officers?'
        . '|(?:rush|social|risk|recruitment|philanthropy)\s+chairs?|pledge\s+(?:master|educator)s?|new\s+member\s+educators?'
        . '|(?:athletic\s+)?trainers?|professors?(?:\s+of\s+\p{L}+)?|instructors?|lecturers?|advis[eo]rs?';
    private const TEAMS = 'soccer|football|basketball|baseball|softball|lacrosse|hockey|swim(?:ming)?|diving|track|cross[\s-]country'
        . '|tennis|volleyball|rugby|wrestling|golf|crew|rowing|cheer(?:leading)?|gymnastics|water\s+polo|fencing|dance|debate|band';

    private const GREEK_LETTERS = 'alpha|beta|gamma|delta|delt|epsilon|zeta|eta|theta|iota|kappa|lambda|mu|nu|xi|omicron|pi|rho|sigma|sig|tau|upsilon|phi|chi|psi|omega|tri';

    /** Well-known chapter abbreviations, matched case-sensitively. */
    private const GREEK_ABBREVIATIONS = 'SAE|ZBT|KKG|DKE|TKE|ATO|AEPi|SigEp|SigChi|ADPi|AXO|AOPi|ZTA|AGD|SDT|AKL|KD|DG|DZ|Pike|Fiji|Teke|Theta|Delt|Delts|Sig Ep|Chi O|Pi Phi|Tri Delt|Phi Psi|Phi Delt';

    private const STREET_TYPES = 'street|st|avenue|ave|road|rd|boulevard|blvd|drive|dr|lane|ln|court|ct|way|place|pl|terrace|ter|circle|cir|parkway|pkwy|highway|hwy|trail|trl|square|sq';

    /** Capitalised only because they open a sentence; trimmed from the front of a name. */
    private const SENTENCE_STARTERS = [
        'then', 'so', 'later', 'next', 'there', 'one', 'last', 'first', 'every', 'some', 'my', 'i', 'yes', 'no',
        'also', 'even', 'once', 'because', 'if', 'though', 'although', 'where', 'why', 'how', 'what', 'who', 'which',
        'eventually', 'finally', 'afterward', 'afterwards', 'soon', 'now', 'today', 'yesterday', 'only', 'nobody', 'everyone',
    ];

    private static ?array $firstNames = null;

    /**
     * @param list<string> $ignoreWords words that are not names here, e.g. the school's name
     * @return list<array{type: string, text: string, start: int, end: int}>
     *         byte offsets into $text, in order, never overlapping
     */
    public function scan(string $text, array $ignoreWords = []): array
    {
        if (trim($text) === '') {
            return [];
        }

        $found = [];
        $add = static function (string $type, string $match, int $start) use (&$found): void {
            $trimmed = rtrim($match, " \t.,;:");
            if ($trimmed !== '') {
                $found[] = ['type' => $type, 'text' => $trimmed, 'start' => $start, 'end' => $start + strlen($trimmed)];
            }
        };
        $matchAll = static function (string $type, string $pattern, int $group = 0) use ($text, $add): void {
            if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[$group] as [$match, $offset]) {
                    if ($offset >= 0) {
                        $add($type, $match, $offset);
                    }
                }
            }
        };

        $matchAll('email', '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/u');

        $matchAll('handle', '~\b(?:https?://)?(?:www\.)?(?:instagram|tiktok|twitter|x|facebook|fb|snapchat|linkedin|youtube|reddit|discord|venmo|threads)\.(?:com|net|gg|me)/[^\s,;)]+~iu');
        $matchAll('handle', '/(?<![\p{L}\p{N}@._%+\-])@[A-Za-z0-9_.]{2,30}/u');
        $matchAll('handle', '/\b(?:snap(?:chat)?|insta(?:gram)?|ig|tiktok|twitter|venmo|discord)\s*[:=]\s*@?[A-Za-z0-9_.]{3,30}/iu');

        $matchAll('phone', '/(?<![\d\-])(?:\+?1[\s.\-]?)?\(?\d{3}\)?[\s.\-]?\d{3}[\s.\-]\d{4}(?![\d\-])/u');
        $matchAll('phone', '/(?<!\d)\d{10}(?!\d)/u');
        $matchAll('phone', '/(?<![\d\-])\d{3}-\d{4}(?![\d\-])/u');

        $matchAll('address', '/\b\d{1,6}\s+(?:[NSEW]\.?\s+)?(?:[\p{L}\p{N}\'’]+\s+){1,4}(?:' . self::STREET_TYPES . ')\b\.?/iu');
        $matchAll('address', '/\b(?:on|at)\s+((?:\p{Lu}[\p{L}\'’]+\s+){1,3}(?:Street|Avenue|Road|Boulevard|Drive|Lane|Court|Way|Place|Terrace|Circle|Parkway|Highway))\b/u', 1);

        $matchAll('room', '/\b(?:room|rm|suite|ste|apt|apartment|unit|dorm|floor)\.?\s*#?\s*[A-Za-z]?\d{1,4}[A-Za-z]?\b/iu');
        $matchAll('room', '/(?<![\p{L}\p{N}])#\s?\d{1,4}[A-Za-z]?\b/u');
        $matchAll('room', '/\b\p{Lu}[\p{L}\'’]+\s+(?:Hall|House|Tower|Towers|Commons|Village|Residence)\s*#?\s*\d{1,4}[A-Za-z]?\b/u');

        $matchAll('greek', '/\b(?:' . self::GREEK_LETTERS . ')(?:\s+(?:' . self::GREEK_LETTERS . ')){1,2}\b/iu');
        $matchAll('greek', '/[\x{0391}-\x{03A9}]{2,3}/u');
        $matchAll('greek', '/(?<![\p{L}])(?:' . self::GREEK_ABBREVIATIONS . ')(?![\p{L}])/u');

        // "my RA", "the coach", "my chemistry professor", "team captain": a
        // determiner and one describing word may come first, or qualifiers alone.
        $matchAll('role', '/(?<![\p{L}\p{N}])(?:(?:' . self::ROLE_DETERMINERS . ')\s+(?:\p{L}+\s+)?|(?:(?:' . self::ROLE_QUALIFIERS . ')\s+)+)?'
            . '(?:' . self::ROLE_ABBREVIATIONS . '|' . self::ROLES . ')(?![\p{L}])/iu');
        // "the soccer team", "my women's lacrosse team"
        $matchAll('role', '/(?<![\p{L}\p{N}])(?:(?:' . self::ROLE_DETERMINERS . ')\s+)?(?:(?:men\'?s|women\'?s|varsity|club|JV|intramural)\s+)?(?:' . self::TEAMS . ')\s+team(?![\p{L}])/iu');

        foreach ($this->names($text, $ignoreWords) as [$match, $offset]) {
            $add('name', $match, $offset);
        }

        return self::withoutOverlaps($found);
    }

    /**
     * The text cut into pieces, each either plain or flagged, for showing
     * with highlights. Joined back together they are exactly $text.
     *
     * @return list<array{text: string, type: ?string, label: ?string}>
     */
    public function segments(string $text, array $findings): array
    {
        $segments = [];
        $position = 0;

        foreach ($findings as $finding) {
            if ($finding['start'] > $position) {
                $segments[] = ['text' => substr($text, $position, $finding['start'] - $position), 'type' => null, 'label' => null];
            }
            $segments[] = ['text' => substr($text, $finding['start'], $finding['end'] - $finding['start']), 'type' => $finding['type'], 'label' => self::LABELS[$finding['type']] ?? null];
            $position = $finding['end'];
        }

        if ($position < strlen($text)) {
            $segments[] = ['text' => substr($text, $position), 'type' => null, 'label' => null];
        }

        return $segments;
    }

    /** @return list<array{0: string, 1: int}> */
    private function names(string $text, array $ignoreWords): array
    {
        $names = [];

        // Two or more capitalised words, or a title and a name ("Coach Miller").
        // A sentence's opening word is capitalised too: "Then Coach Miller"
        // is highlighted as "Coach Miller".
        foreach (NameDetector::find($text, $ignoreWords) as $phrase) {
            $words = explode(' ', $phrase);
            while (count($words) > 1 && in_array(mb_strtolower($words[0]), self::SENTENCE_STARTERS, true)) {
                array_shift($words);
            }
            $phrase = implode(' ', $words);
            if (count($words) < 2 && !preg_match('/^\p{Lu}/u', $phrase)) {
                continue;
            }

            if (preg_match_all('/(?<![\p{L}])' . preg_quote($phrase, '/') . '(?![\p{L}])/u', $text, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as [$match, $offset]) {
                    $names[] = [$match, $offset];
                }
            }
        }

        // A single first name. Ambiguous ones only away from the start of a sentence.
        $list = self::firstNames();
        $ignored = array_flip(array_map('mb_strtolower', $ignoreWords));
        if (preg_match_all('/(?<![\p{L}\'’])\p{Lu}[\p{Ll}\'’]+(?![\p{L}])/u', $text, $words, PREG_OFFSET_CAPTURE)) {
            foreach ($words[0] as [$word, $offset]) {
                $lower = mb_strtolower(preg_replace("/['’]s$/u", '', $word) ?? $word);
                if (isset($ignored[$lower])) {
                    continue;
                }
                if (isset($list['names'][$lower]) || (isset($list['ambiguous'][$lower]) && !self::startsSentence($text, $offset))) {
                    $names[] = [$word, $offset];
                }
            }
        }

        // "a guy named Brock", "his name was Thorne"
        if (preg_match_all('/\b(?:named|called|name\s+(?:is|was))\s+(\p{Lu}[\p{L}\'’\-]+)/u', $text, $called, PREG_OFFSET_CAPTURE)) {
            foreach ($called[1] as [$word, $offset]) {
                $names[] = [$word, $offset];
            }
        }

        return $names;
    }

    private static function startsSentence(string $text, int $offset): bool
    {
        $before = rtrim(substr($text, 0, $offset), " \t\"'“‘(");

        return $before === '' || preg_match('/[.!?\n:]$/u', $before) === 1;
    }

    /** @return array{names: array<string, true>, ambiguous: array<string, true>} */
    private static function firstNames(): array
    {
        if (self::$firstNames === null) {
            $lists = require __DIR__ . '/first-names.php';
            self::$firstNames = [
                'names' => array_fill_keys($lists['names'], true),
                'ambiguous' => array_fill_keys($lists['ambiguous'], true),
            ];
        }

        return self::$firstNames;
    }

    /**
     * Earlier first; at the same start, the longer one. A finding inside one
     * already kept is dropped, so "Sigma Chi house" is not also a name.
     */
    private static function withoutOverlaps(array $found): array
    {
        $priority = array_flip(array_keys(self::LABELS));
        usort($found, static fn (array $a, array $b): int => [$a['start'], $b['end'], $priority[$a['type']]] <=> [$b['start'], $a['end'], $priority[$b['type']]]);

        $kept = [];
        $reach = -1;
        foreach ($found as $finding) {
            if ($finding['start'] < $reach) {
                continue;
            }
            $kept[] = $finding;
            $reach = $finding['end'];
        }

        return $kept;
    }
}
