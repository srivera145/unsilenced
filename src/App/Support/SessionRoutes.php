<?php

namespace Keel\App\Support;

/**
 * The only paths that start a PHP session.
 *
 * WHY THIS IS AN ALLOWLIST. Keel's front controller used to call
 * Session::start() on every request, and session_start() sets a PHPSESSID
 * cookie on any browser that does not already have one. On this site that
 * cookie is a record, left on a shared or monitored device, that someone
 * visited. Public pages need no session at all, so the default is no session
 * and only the admin panel and its sign-in flow opt in.
 *
 * This is the reverse of Curbline's PublicRoutes, which lists the sessionless
 * paths. Here a newly added public route is cookie-free without anyone having
 * to remember to list it.
 *
 * It decides before the router is built (the session has to start first), so
 * it cannot read route registration. A new admin path must live under one of
 * these prefixes. SessionRoutesTest asserts every route behind AuthMiddleware
 * does.
 */
class SessionRoutes
{
    public const PREFIXES = [
        '/admin',
        '/login',
        '/logout',
        '/auth',
    ];

    public static function requiresSession(string $path): bool
    {
        $path = '/' . ltrim($path, '/');

        foreach (self::PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }
}
