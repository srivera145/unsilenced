<?php

namespace Keel\App\Support;

/**
 * Keeps sign-in links and links to admin pages working with a SameSite=Strict
 * session cookie.
 *
 * A browser leaves a Strict cookie off any request that starts on another
 * site: the sign-in link in an email read in webmail, or a link to an admin
 * page pasted in a chat. PHP would see no session, start a new one, and send a
 * cookie that replaces the admin's real one (and after a magic link's redirect
 * to /admin, the new session would be missing too).
 *
 * So a cross-site GET to a session path that arrives without the cookie gets a
 * one-line page that loads the same address again from this site. That second
 * request is same-site: it carries the cookie if the browser has one, and the
 * redirects after it are same-site too. Browsers that send no Sec-Fetch-Site
 * header (Safari before 16.4) skip the hop and behave as before.
 *
 * public_html/index.php sends the page before any session starts.
 */
final class SameSiteHop
{
    /**
     * @param array<string, mixed> $server $_SERVER
     * @param array<string, mixed> $cookies $_COOKIE
     */
    public static function needed(array $server, array $cookies, string $sessionCookie): bool
    {
        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));

        return ($method === 'GET' || $method === 'HEAD')
            && !isset($cookies[$sessionCookie])
            && strtolower(trim((string) ($server['HTTP_SEC_FETCH_SITE'] ?? ''))) === 'cross-site';
    }

    /**
     * The same path and query string as a same-origin path. Leading slashes
     * are collapsed, so "//elsewhere.example/admin" cannot become a link to
     * another site.
     */
    public static function target(string $path, string $query): string
    {
        $target = '/' . ltrim($path, '/\\');

        return $query === '' ? $target : $target . '?' . $query;
    }

    public static function page(string $target): string
    {
        $url = htmlspecialchars($target, ENT_QUOTES, 'UTF-8');

        return <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
            <meta charset="UTF-8">
            <meta name="robots" content="noindex, nofollow">
            <meta http-equiv="refresh" content="0;url={$url}">
            <title>Unsilenced</title>
            </head>
            <body>
            <p><a href="{$url}">Continue</a></p>
            </body>
            </html>

            HTML;
    }
}
