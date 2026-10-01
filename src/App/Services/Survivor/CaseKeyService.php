<?php

namespace Keel\App\Services\Survivor;

use Keel\App\Support\Config;
use Keel\Core\Database;

/**
 * Case keys: the only way back into a report. No account, no email needed.
 *
 * A key is six words drawn at random (random_int, a CSPRNG) from
 * case-key-words.txt: 5,600 words, so six carry about 74.7 bits. It is shown
 * once and never stored. The database keeps:
 *
 *   lookup_id  HMAC-SHA256 of the key under a key derived from
 *              VAULT_MASTER_KEY: finds the row without storing the key, and
 *              is useless without the master key.
 *   key_hash   Argon2id of the key: confirms it.
 *
 * Keys are compared after normalising: lower case, words separated by single
 * spaces, so "Maple-Otter  BRISK" and "maple otter brisk" are the same.
 */
final class CaseKeyService
{
    private static ?array $words = null;

    /** @return list<string> */
    public static function words(): array
    {
        if (self::$words === null) {
            $lines = file(__DIR__ . '/case-key-words.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            self::$words = array_values(array_unique(array_map('trim', $lines)));
        }

        return self::$words;
    }

    public static function wordCount(): int
    {
        return max(1, (int) Config::get('survivor_reports.case_key_words', 6));
    }

    public static function entropyBits(): float
    {
        return self::wordCount() * log(count(self::words()), 2);
    }

    public function generate(): string
    {
        $words = self::words();
        $chosen = [];
        for ($i = 0; $i < self::wordCount(); $i++) {
            $chosen[] = $words[random_int(0, count($words) - 1)];
        }

        return implode(' ', $chosen);
    }

    public static function normalize(string $input): string
    {
        $lower = mb_strtolower($input, 'UTF-8');
        $words = preg_split('/[^a-z]+/', $lower, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', $words);
    }

    /**
     * Words in what was typed that are not in the list, for a gentler error
     * ("check the spelling of ...") than "wrong key". Says nothing about
     * whether a case exists.
     *
     * @return list<string>
     */
    public static function unknownWords(string $input): array
    {
        $known = array_flip(self::words());

        return array_values(array_filter(
            explode(' ', self::normalize($input)),
            static fn (string $word): bool => $word !== '' && !isset($known[$word])
        ));
    }

    public function lookupId(string $key): string
    {
        $lookupKey = VaultKeys::require()->lookup();
        $id = hash_hmac('sha256', self::normalize($key), $lookupKey);
        sodium_memzero($lookupKey);

        return $id;
    }

    public function hash(string $key): string
    {
        return password_hash(self::normalize($key), PASSWORD_ARGON2ID);
    }

    public function verify(string $key, string $hash): bool
    {
        return password_verify(self::normalize($key), $hash);
    }

    /** The case this key opens, or null. Argon2 runs only when the lookup id matches a row. */
    public function findCase(string $key): ?array
    {
        $normalized = self::normalize($key);
        if (count(explode(' ', $normalized)) !== self::wordCount()) {
            return null;
        }

        $statement = Database::connection()->prepare('SELECT * FROM survivor_cases WHERE lookup_id = ? LIMIT 1');
        $statement->execute([$this->lookupId($normalized)]);
        $case = $statement->fetch() ?: null;

        return $case !== null && $this->verify($normalized, (string) $case['key_hash']) ? $case : null;
    }
}
