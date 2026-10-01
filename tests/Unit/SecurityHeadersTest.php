<?php

declare(strict_types=1);

namespace Tests\Unit;

use Keel\App\Support\SameSiteHop;
use Keel\Core\Request;
use Keel\Core\SecurityHeaders;
use Keel\Core\Session;
use PHPUnit\Framework\TestCase;

class SecurityHeadersTest extends TestCase
{
    private array $server = [];
    private array $env = [];

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $this->env = $_ENV;
        unset($_SERVER['HTTPS'], $_SERVER['REQUEST_SCHEME'], $_SERVER['HTTP_X_FORWARDED_PROTO'], $_ENV['TRUST_PROXY'], $_SERVER['TRUST_PROXY']);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $_ENV = $this->env;
    }

    /** @return array<string, string> directive => value */
    private static function directives(string $policy): array
    {
        $directives = [];
        foreach (array_filter(array_map('trim', explode(';', $policy))) as $directive) {
            [$name, $value] = array_pad(explode(' ', $directive, 2), 2, '');
            $directives[$name] = $value;
        }

        return $directives;
    }

    public function testCspAllowsNoInlineScriptOrStyleAndNothingFromOtherOrigins(): void
    {
        $csp = self::directives(SecurityHeaders::all(false)['Content-Security-Policy']);

        self::assertSame("'self'", $csp['script-src']);
        self::assertSame("'self'", $csp['style-src']);
        self::assertSame("'self'", $csp['default-src']);
        self::assertSame("'none'", $csp['object-src']);
        self::assertSame("'none'", $csp['frame-ancestors']);
        self::assertStringNotContainsString('unsafe-inline', SecurityHeaders::CONTENT_SECURITY_POLICY);
        self::assertStringNotContainsString('unsafe-eval', SecurityHeaders::CONTENT_SECURITY_POLICY);
        self::assertDoesNotMatchRegularExpression('#https?:|\*#', SecurityHeaders::CONTENT_SECURITY_POLICY);
    }

    public function testHstsOnlyOverHttps(): void
    {
        self::assertArrayNotHasKey('Strict-Transport-Security', SecurityHeaders::all(false));
        self::assertSame('max-age=31536000', SecurityHeaders::all(true)['Strict-Transport-Security']);

        foreach (['X-Content-Type-Options' => 'nosniff', 'X-Frame-Options' => 'DENY', 'Referrer-Policy' => 'no-referrer'] as $name => $value) {
            self::assertSame($value, SecurityHeaders::all(false)[$name]);
            self::assertSame($value, SecurityHeaders::all(true)[$name]);
        }
    }

    public function testHttpsDetection(): void
    {
        self::assertFalse(Request::isHttps());

        $_SERVER['HTTPS'] = 'off';
        self::assertFalse(Request::isHttps(), 'IIS sets HTTPS=off on plain HTTP');
        $_SERVER['HTTPS'] = 'on';
        self::assertTrue(Request::isHttps());
        unset($_SERVER['HTTPS']);

        $_SERVER['REQUEST_SCHEME'] = 'https';
        self::assertTrue(Request::isHttps());
        unset($_SERVER['REQUEST_SCHEME']);

        // A forwarded header counts only when the proxy is trusted: otherwise
        // any client could send it.
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        self::assertFalse(Request::isHttps());
        $_ENV['TRUST_PROXY'] = 'true';
        self::assertTrue(Request::isHttps());
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'http, https';
        self::assertFalse(Request::isHttps(), 'the first value is the one the visitor used');
    }

    public function testAdminSessionCookieIsStrictHttpOnlyAndSecureOnHttps(): void
    {
        self::assertSame(
            ['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Strict', 'secure' => false],
            Session::cookieParams(false)
        );
        self::assertTrue(Session::cookieParams(true)['secure']);
        self::assertSame(7200, Session::IDLE_TIMEOUT_SECONDS);
    }

    public function testCrossSiteLinksToSessionPagesHopThroughThisSiteFirst(): void
    {
        $crossSite = ['REQUEST_METHOD' => 'GET', 'HTTP_SEC_FETCH_SITE' => 'cross-site'];

        self::assertTrue(SameSiteHop::needed($crossSite, [], 'PHPSESSID'));
        self::assertTrue(SameSiteHop::needed(['REQUEST_METHOD' => 'HEAD'] + $crossSite, [], 'PHPSESSID'));
        // The cookie came along, the request started on this site, the
        // browser sends no Sec-Fetch-Site, or it is not a GET: no hop.
        self::assertFalse(SameSiteHop::needed($crossSite, ['PHPSESSID' => 'abc'], 'PHPSESSID'));
        self::assertFalse(SameSiteHop::needed(['HTTP_SEC_FETCH_SITE' => 'same-origin'] + $crossSite, [], 'PHPSESSID'));
        self::assertFalse(SameSiteHop::needed(['HTTP_SEC_FETCH_SITE' => 'none'] + $crossSite, [], 'PHPSESSID'));
        self::assertFalse(SameSiteHop::needed(['REQUEST_METHOD' => 'GET'], [], 'PHPSESSID'));
        self::assertFalse(SameSiteHop::needed(['REQUEST_METHOD' => 'POST'] + $crossSite, [], 'PHPSESSID'));

        self::assertSame('/auth/magic?email=a%40b.org&token=abc', SameSiteHop::target('/auth/magic', 'email=a%40b.org&token=abc'));
        self::assertSame('/admin', SameSiteHop::target('/admin', ''));
        self::assertSame('/elsewhere.example/admin', SameSiteHop::target('//elsewhere.example/admin', ''), 'never a protocol-relative URL');
        self::assertSame('/elsewhere.example/admin', SameSiteHop::target('/\\elsewhere.example/admin', ''));

        $page = SameSiteHop::page('/auth/magic?email=a%40b.org&token="><script>');
        self::assertStringContainsString('<meta http-equiv="refresh" content="0;url=/auth/magic?email=a%40b.org&amp;token=&quot;&gt;&lt;script&gt;">', $page);
        self::assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $page);
        self::assertStringNotContainsString('<script', $page);
    }
}
