<?php

namespace Keel\App\Middleware;

use Keel\App\Support\SessionRoutes;
use Keel\App\Support\SurvivorSession;
use Keel\Core\Middleware;
use Keel\Core\Request;

/**
 * Every survivor page (/submit, /my-report, /share):
 *
 * - Cache-Control: no-store, so no page with her report, her key or her files
 *   is kept in the browser's cache or restored by Back from it;
 * - X-Robots-Tag: noindex;
 * - the 30-minute idle timeout: an area untouched that long forgets what it
 *   held (who is signed in to /my-report, which share link is open) before
 *   the request runs, and the next page says why.
 */
class SurvivorSessionMiddleware implements Middleware
{
    public function handle(Request $request, \Closure $next): mixed
    {
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex, nofollow');

        $area = SessionRoutes::area($request->uri);

        if (SessionRoutes::isSurvivorArea($area)) {
            if (SurvivorSession::isIdleExpired($area)) {
                $wasOpen = $area === '/my-report' ? SurvivorSession::caseId() !== null : ($area === '/share' && SurvivorSession::shareLinkId() !== null);
                SurvivorSession::clear($area);
                if ($wasOpen) {
                    SurvivorSession::flash($area, 'timed_out');
                }
            }

            SurvivorSession::touch($area);
        }

        return $next($request);
    }
}
