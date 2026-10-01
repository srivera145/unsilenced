<?php

namespace Keel\Core;

/**
 * The headers public_html/index.php sends with every response.
 */
final class SecurityHeaders
{
    /**
     * Same-origin everything. This is the enforcement behind "zero third-party
     * scripts": a script, stylesheet, font, image or fetch from any other
     * origin is blocked by the browser even if a view were edited to include
     * one.
     *
     * No 'unsafe-inline' for scripts or styles: an injected <script>, inline
     * event handler or style="" attribute does not run. Views use static files
     * in public_html/js and classes in public_html/css/keel.css instead, and
     * views/partials/head.php prints Deck's tags without Deck::head()'s inline
     * script. JSON-LD blocks (<script type="application/ld+json">) are data,
     * which CSP does not apply to. Setting element.style from a script (the
     * quick exit blanks the page that way) is not an inline style and is
     * allowed.
     */
    public const CONTENT_SECURITY_POLICY = "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'";

    /**
     * One year. Sent only over HTTPS: browsers ignore it over HTTP, and a
     * development site on plain HTTP must not be pinned. No includeSubDomains
     * or preload until the production domain and its subdomains are settled.
     */
    public const STRICT_TRANSPORT_SECURITY = 'max-age=31536000';

    /** @return array<string, string> header name => value */
    public static function all(bool $https): array
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            // no-referrer: the quick-exit destination and every outbound link
            // (RAINN, news sources) must not learn the visitor came from here.
            'Referrer-Policy' => 'no-referrer',
            'Content-Security-Policy' => self::CONTENT_SECURITY_POLICY,
            'Permissions-Policy' => 'browsing-topics=(), interest-cohort=(), camera=(), microphone=(), geolocation=()',
        ];

        if ($https) {
            $headers['Strict-Transport-Security'] = self::STRICT_TRANSPORT_SECURITY;
        }

        return $headers;
    }

    public static function send(): void
    {
        header_remove('X-Powered-By');

        foreach (self::all(Request::isHttps()) as $name => $value) {
            header($name . ': ' . $value);
        }
    }
}
