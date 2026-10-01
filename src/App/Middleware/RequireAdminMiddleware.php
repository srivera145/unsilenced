<?php

namespace Keel\App\Middleware;

use Keel\App\Models\User;
use Keel\Core\Middleware;
use Keel\Core\Request;
use Keel\Core\Response;
use Keel\Core\Session;

/**
 * The admin panel's gate. Runs after AuthMiddleware, which has already sent a
 * signed-out visitor to /login. A signed-in user without is_admin = 1 gets a
 * 403; there is nothing else in this app for them to see.
 */
class RequireAdminMiddleware implements Middleware
{
    public function handle(Request $request, \Closure $next): mixed
    {
        $user = User::find((int) Session::get('user_id'));

        if (!User::isAdmin($user)) {
            if ($request->wantsJson()) {
                Response::json(['error' => 'Admin access is required.'], 403);
            }

            Response::abort(403, 'Admin access is required.');
        }

        return $next($request);
    }
}
