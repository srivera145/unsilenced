<?php

namespace Keel\App\Middleware;

use Keel\App\Support\Submissions;
use Keel\Core\Middleware;
use Keel\Core\Request;
use Keel\Core\View;

/**
 * First in front of /submit, /my-report and /share. While SUBMISSIONS_ENABLED
 * is false, or the vault is not set up safely (Submissions::problem()), every
 * one of these pages, GET or POST, is the "coming soon" page with the
 * hotline. Nothing runs behind it: no session was started (SessionRoutes),
 * nothing is read or stored.
 */
class SubmissionsEnabledMiddleware implements Middleware
{
    public function handle(Request $request, \Closure $next): mixed
    {
        if (!Submissions::ready()) {
            header('Cache-Control: no-store');
            View::render('survivor.coming-soon', ['title' => 'Coming soon', 'noindex' => true]);

            return null;
        }

        return $next($request);
    }
}
