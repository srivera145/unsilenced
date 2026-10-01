<?php

namespace Keel\App\Support;

use Keel\Core\Session;

/**
 * What the survivor pages keep in their session, and their 30-minute idle
 * timeout. Never the case key, the account, a file name or a share token:
 * only ids, the form's CSRF token and proof-of-work challenge, and times.
 *
 * In production each area (/submit, /my-report, /share) has its own cookie
 * and session; keys are prefixed by area anyway, so they cannot collide when
 * the test harness shares one session between them.
 */
final class SurvivorSession
{
    public static function idleSeconds(): int
    {
        return 60 * max(1, (int) Config::get('survivor_reports.session_idle_minutes', 30));
    }

    /** Records a request now. */
    public static function touch(string $area, ?int $now = null): void
    {
        Session::put('survivor.' . $area . '.last', $now ?? time());
    }

    /** True when the area has been idle past the timeout. A session never touched is not expired: it is new. */
    public static function isIdleExpired(string $area, ?int $now = null): bool
    {
        $last = (int) Session::get('survivor.' . $area . '.last', 0);

        return $last > 0 && ($now ?? time()) - $last > self::idleSeconds();
    }

    /** Forgets everything the area holds, keeping nothing of what was there. */
    public static function clear(string $area): void
    {
        foreach (array_keys($_SESSION ?? []) as $key) {
            if (str_starts_with((string) $key, 'survivor.' . $area . '.')) {
                Session::forget((string) $key);
            }
        }
    }

    // --- /my-report -------------------------------------------------------------

    public static function caseId(): ?int
    {
        $id = Session::get('survivor./my-report.case_id');

        return is_int($id) && $id > 0 ? $id : null;
    }

    public static function signIn(int $caseId): void
    {
        if (Session::isActive() && !\Keel\Core\Response::isCapturing()) {
            Session::regenerate();
        }
        Session::put('survivor./my-report.case_id', $caseId);
        self::touch('/my-report');
    }

    public static function signOut(): void
    {
        self::clear('/my-report');
    }

    // --- /share -------------------------------------------------------------------

    public static function shareLinkId(): ?int
    {
        $id = Session::get('survivor./share.link_id');

        return is_int($id) && $id > 0 ? $id : null;
    }

    public static function openShareLink(int $linkId): void
    {
        if (Session::isActive() && !\Keel\Core\Response::isCapturing()) {
            Session::regenerate();
        }
        Session::put('survivor./share.link_id', $linkId);
        self::touch('/share');
    }

    // --- a one-off notice on the next page --------------------------------------

    public static function flash(string $area, string $notice): void
    {
        Session::put('survivor.' . $area . '.notice', $notice);
    }

    public static function takeFlash(string $area): ?string
    {
        $notice = Session::get('survivor.' . $area . '.notice');
        Session::forget('survivor.' . $area . '.notice');

        return is_string($notice) ? $notice : null;
    }
}
