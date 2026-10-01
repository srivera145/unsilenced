<?php

namespace Keel\App\Services\Survivor;

use Keel\App\Support\Config;
use Keel\Core\Session;

/**
 * Spam protection without a CAPTCHA service or anyone's IP address.
 *
 * The form page gets a random challenge, kept in the /submit session. On
 * the final submit the browser (public_html/js/report-form.js) searches for a
 * number such that SHA-256("challenge:number") starts with N zero bits: a
 * few seconds on a phone, nothing for a person, real cost for a bot sending
 * thousands. The server checks it with one hash. A challenge is used once:
 * a resubmitted form (Back, then Forward) fails, so it cannot create a second
 * report.
 */
final class ProofOfWork
{
    private const SESSION_KEY = 'survivor.pow';

    public static function bits(): int
    {
        return max(1, min(32, (int) Config::get('survivor_reports.proof_of_work_bits', 18)));
    }

    /** The current challenge, issuing one if there is none. @return array{challenge: string, bits: int} */
    public static function challenge(): array
    {
        $current = Session::get(self::SESSION_KEY);
        if (is_array($current) && isset($current['challenge'], $current['bits'])) {
            return ['challenge' => (string) $current['challenge'], 'bits' => (int) $current['bits']];
        }

        $issued = ['challenge' => bin2hex(random_bytes(16)), 'bits' => self::bits()];
        Session::put(self::SESSION_KEY, $issued);

        return $issued;
    }

    public static function verify(string $challenge, string $nonce): bool
    {
        $issued = Session::get(self::SESSION_KEY);
        if (!is_array($issued) || !isset($issued['challenge']) || !hash_equals((string) $issued['challenge'], $challenge)) {
            return false;
        }

        if (!preg_match('/^\d{1,15}$/', $nonce)) {
            return false;
        }

        return self::leadingZeroBits(hash('sha256', $challenge . ':' . $nonce, true)) >= (int) $issued['bits'];
    }

    /**
     * Spends the challenge. The next form gets a new one, and this one is
     * remembered (the last five), so the same form arriving again is
     * recognised as already sent.
     */
    public static function consume(): void
    {
        $current = Session::get(self::SESSION_KEY);
        if (is_array($current) && isset($current['challenge'])) {
            $spent = (array) Session::get(self::SESSION_KEY . '_spent', []);
            $spent[] = hash('sha256', (string) $current['challenge']);
            Session::put(self::SESSION_KEY . '_spent', array_slice($spent, -5));
        }

        Session::forget(self::SESSION_KEY);
    }

    public static function isSpent(string $challenge): bool
    {
        return $challenge !== '' && in_array(hash('sha256', $challenge), (array) Session::get(self::SESSION_KEY . '_spent', []), true);
    }

    public static function leadingZeroBits(string $binary): int
    {
        $bits = 0;
        $length = strlen($binary);
        for ($i = 0; $i < $length; $i++) {
            $byte = ord($binary[$i]);
            if ($byte === 0) {
                $bits += 8;
                continue;
            }

            for ($mask = 0x80; $mask > 0 && ($byte & $mask) === 0; $mask >>= 1) {
                $bits++;
            }

            break;
        }

        return $bits;
    }

    /** What the browser does, for tests and the walkthrough. */
    public static function solve(string $challenge, int $bits): string
    {
        for ($nonce = 0; ; $nonce++) {
            if (self::leadingZeroBits(hash('sha256', $challenge . ':' . $nonce, true)) >= $bits) {
                return (string) $nonce;
            }
        }
    }
}
