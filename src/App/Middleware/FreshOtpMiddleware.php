<?php

namespace Keel\App\Middleware;

use Keel\App\Support\Config;
use Keel\Core\Middleware;
use Keel\Core\Request;
use Keel\Core\Response;
use Keel\Core\Session;

/**
 * In front of every admin route that shows evidence. The admin must have
 * entered an emailed code within the last admin_otp_fresh_minutes (15):
 * signing in by code counts, a magic link does not. Otherwise they are sent
 * to /admin/verify for a new code, and back here afterwards.
 */
class FreshOtpMiddleware implements Middleware
{
    public const SESSION_KEY = 'otp_verified_at';
    public const RETURN_KEY = 'admin.after_verify';

    public static function isFresh(?int $now = null): bool
    {
        $verifiedAt = (int) Session::get(self::SESSION_KEY, 0);
        $window = 60 * max(1, (int) Config::get('survivor_reports.admin_otp_fresh_minutes', 15));

        return $verifiedAt > 0 && ($now ?? time()) - $verifiedAt <= $window;
    }

    public function handle(Request $request, \Closure $next): mixed
    {
        if (!self::isFresh()) {
            // Only this site's own admin paths are remembered for the return trip.
            if ($request->method === 'GET' && str_starts_with($request->uri, '/admin/')) {
                Session::put(self::RETURN_KEY, $request->uri);
            }

            Response::redirect('/admin/verify');
        }

        return $next($request);
    }
}
