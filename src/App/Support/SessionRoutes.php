<?php

namespace Keel\App\Support;

use Keel\App\Support\Submissions;

/**
 * The only paths that start a PHP session.
 *
 * WHY THIS IS AN ALLOWLIST. Keel's front controller used to call
 * Session::start() on every request, and session_start() sets a PHPSESSID
 * cookie on any browser that does not already have one. On this site that
 * cookie is a record, left on a shared or monitored device, that someone
 * visited. Public pages need no session at all, so the default is no session
 * and only these opt in:
 *
 *   admin     /admin, /login, /logout, /auth. PHPSESSID, path /, idle 2 hours.
 *   survivor  /submit, /my-report, /share (Phase 2). Each has its own cookie,
 *             named "sid" with the area's path, so it is sent only to that
 *             area's pages and never to a public page; idle 30 minutes; its
 *             own session directory. Only while submissions are enabled
 *             (Submissions::ready()); otherwise these pages are a "coming
 *             soon" page and set nothing.
 *
 * This is the reverse of Curbline's PublicRoutes, which lists the sessionless
 * paths. Here a newly added public route is cookie-free without anyone having
 * to remember to list it.
 *
 * It decides before the router is built (the session has to start first), so
 * it cannot read route registration. A new admin or survivor path must live
 * under one of these prefixes. SessionRoutesTest asserts every route with
 * session middleware does.
 */
class SessionRoutes
{
    public const PREFIXES = [
        '/admin',
        '/login',
        '/logout',
        '/auth',
    ];

    public const SURVIVOR_PREFIXES = [
        '/submit',
        '/my-report',
        '/share',
    ];

    public const SURVIVOR_COOKIE = 'sid';

    public static function requiresSession(string $path): bool
    {
        return self::area($path) !== null;
    }

    /** 'admin', a survivor prefix ('/submit', '/my-report', '/share'), or null for a public path. */
    public static function area(string $path): ?string
    {
        $path = '/' . ltrim($path, '/');

        foreach (self::PREFIXES as $prefix) {
            if (self::under($path, $prefix)) {
                return 'admin';
            }
        }

        foreach (self::SURVIVOR_PREFIXES as $prefix) {
            if (self::under($path, $prefix)) {
                return $prefix;
            }
        }

        return null;
    }

    public static function isSurvivorArea(?string $area): bool
    {
        return $area !== null && in_array($area, self::SURVIVOR_PREFIXES, true);
    }

    /**
     * Options for Session::start(), or null when this request starts no
     * session (a public path, or a survivor path while submissions are off).
     */
    public static function sessionOptions(string $path, string $basePath): ?array
    {
        $area = self::area($path);

        if ($area === null) {
            return null;
        }

        if ($area === 'admin') {
            return [];
        }

        if (!Submissions::ready()) {
            return null;
        }

        return [
            'name' => self::SURVIVOR_COOKIE,
            'path' => $area,
            'idle_seconds' => SurvivorSession::idleSeconds(),
            'save_path' => $basePath . '/storage/sessions',
        ];
    }

    private static function under(string $path, string $prefix): bool
    {
        return $path === $prefix || str_starts_with($path, $prefix . '/');
    }
}
