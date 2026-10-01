<?php

namespace Keel\Core;

/**
 * The session. Only the admin panel, its sign-in flow and the survivor pages
 * (/submit, /my-report, /share) start one (Keel\App\Support\SessionRoutes);
 * public pages never set a cookie.
 */
class Session
{
    /** An admin idle this long is signed out (AuthMiddleware). */
    public const IDLE_TIMEOUT_SECONDS = 7200;

    private const LAST_ACTIVITY_KEY = 'last_activity_at';

    /** The cookie path of the session started for this request ('/' for admin). */
    private static string $cookiePath = '/';

    /**
     * @param array{name?: string, path?: string, idle_seconds?: int, save_path?: ?string} $options
     *        name: the cookie's name (default PHPSESSID); path: the cookie's
     *        path, so a survivor cookie is sent only to its own pages;
     *        idle_seconds: how long PHP keeps an untouched session file;
     *        save_path: a directory of its own, so a short lifetime there
     *        never sweeps away admin sessions.
     */
    public static function start(array $options = []): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            self::$cookiePath = (string) ($options['path'] ?? '/');

            // Refuse a session ID the server never issued (session fixation),
            // and let PHP's garbage collector drop files idle past the timeout.
            ini_set('session.use_strict_mode', '1');
            ini_set('session.gc_maxlifetime', (string) ($options['idle_seconds'] ?? self::IDLE_TIMEOUT_SECONDS));

            if (!empty($options['save_path'])) {
                $directory = (string) $options['save_path'];
                if (!is_dir($directory)) {
                    @mkdir($directory, 0700, true);
                }
                session_save_path($directory);
            }

            if (!empty($options['name'])) {
                session_name((string) $options['name']);
            }

            session_set_cookie_params(self::cookieParams(Request::isHttps(), self::$cookiePath));
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
    public static function cookieParams(bool $https, string $path = '/'): array
    {
        return [
            'lifetime' => 0,
            'path' => $path,
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
                $params = self::cookieParams(Request::isHttps(), self::$cookiePath);
                unset($params['lifetime']);
                setcookie($name, '', ['expires' => time() - 3600] + $params);
            }
        }
    }
}
