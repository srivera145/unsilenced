<?php

namespace Keel\App\Support;

use Keel\App\Services\Survivor\VaultService;
use Keel\App\Services\Survivor\VaultKeys;
use Keel\Core\Env;
use Keel\Core\Request;

/**
 * The SUBMISSIONS_ENABLED switch, and whether the site is fit to take a
 * report when it is on.
 *
 * Off (the default, until legal review): /submit, /my-report and /share show
 * a "coming soon" page with the hotline, set no cookie and store nothing; the
 * school-page section and the home-page total are hidden. The admin queue
 * still works, so moderation can be tested.
 *
 * On, the pages still stay "coming soon" unless the vault can work safely:
 * VAULT_MASTER_KEY is 32 bytes of base64, VAULT_PATH is outside
 * public_html, libsodium is loaded, and in production the request came over
 * HTTPS. Each problem is logged (without anything about the visitor) so the
 * operator can see why.
 */
final class Submissions
{
    private static array $logged = [];

    public static function enabled(): bool
    {
        return Env::get('SUBMISSIONS_ENABLED', false) === true;
    }

    public static function ready(): bool
    {
        return self::enabled() && self::problem() === null;
    }

    /** Why an enabled site cannot take reports, or null. */
    public static function problem(): ?string
    {
        $problem = self::vaultProblem() ?? match (true) {
            Env::get('APP_ENV') === 'production' && !Request::isHttps() => 'the request did not come over HTTPS',
            default => null,
        };

        if ($problem !== null && self::enabled() && !isset(self::$logged[$problem])) {
            self::$logged[$problem] = true;
            error_log('[Unsilenced] Submissions are enabled but closed: ' . $problem . '.');
        }

        return $problem;
    }

    /**
     * What the server itself lacks, whatever the request: sodium, a valid
     * VAULT_MASTER_KEY, a vault outside the web root. /up reports 503 for
     * any of these while submissions are enabled.
     */
    public static function vaultProblem(): ?string
    {
        return match (true) {
            !function_exists('sodium_crypto_secretstream_xchacha20poly1305_init_push') => 'the sodium PHP extension is not loaded',
            VaultKeys::current() === null => 'VAULT_MASTER_KEY is missing or is not 32 bytes of base64',
            !VaultService::rootIsSafe() => 'VAULT_PATH is inside public_html',
            default => null,
        };
    }
}
