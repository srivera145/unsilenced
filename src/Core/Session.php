<?php

namespace Keel\Core;

/**
 * The admin session. Only the admin panel and its sign-in flow start one
 * (Keel\App\Support\SessionRoutes); public pages never set a cookie.
 */
class Session
{
    /** An admin idle this long is signed out (AuthMiddleware). */
    public const IDLE_TIMEOUT_SECONDS = 7200;

    private const LAST_ACTIVITY_KEY = 'last_activity_at';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Refuse a session ID the server never issued (session fixation),
            // and let PHP's garbage collector drop files idle past the timeout.
            ini_set('session.use_strict_mode', '1');
            ini_set('session.gc_maxlifetime', (string) self::IDLE_TIMEOUT_SECONDS);
            session_set_cookie_params(self::cookieParams(Request::isHttps()));
            session_start();
        }
    }

    /**
     * The session cookie: gone when the browser closes, unreadable from
     * JavaScript, never sent on a request that starts on another site
     * (SameSite=Strict; SameSiteHop keeps sign-in links working), and over
     * HTTPS only when the site is on HTTPS.
     *
     * @return array{lifetime: int, path: string, httponly: bool, samesite: string, secure: bool}
     */
    public static function cookieParams(bool $https): array
    {
        return [
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Strict',
            'secure' => $https,
        ];
    }

    public static function isActive(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Record activity now: sign-in and every admin request. */
    public static function touch(?int $now = null): void
    {
        self::put(self::LAST_ACTIVITY_KEY, $now ?? time());
    }

    /**
     * True when nothing has happened for longer than IDLE_TIMEOUT_SECONDS, or
     * no activity was ever recorded (a session from before the timeout existed).
     */
    public static function isIdleExpired(?int $now = null): bool
    {
        $last = (int) self::get(self::LAST_ACTIVITY_KEY, 0);

        return $last <= 0 || ($now ?? time()) - $last > self::IDLE_TIMEOUT_SECONDS;
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            $name = session_name();
            session_destroy();

            // session_destroy() leaves the browser's cookie; expire it too.
            if (!headers_sent()) {
                $params = self::cookieParams(Request::isHttps());
                unset($params['lifetime']);
                setcookie($name, '', ['expires' => time() - 3600] + $params);
            }
        }
    }
}
