<?php

declare(strict_types=1);

namespace Tests\Unit;

use Keel\App\Middleware\AuthMiddleware;
use Keel\App\Middleware\CsrfMiddleware;
use Keel\App\Middleware\SurvivorSessionMiddleware;
use Keel\App\Support\SessionRoutes;
use Keel\Core\Router;
use PHPUnit\Framework\TestCase;

class SessionRoutesTest extends TestCase
{
    public function testOnlyAdminSignInAndSurvivorPathsStartASession(): void
    {
        foreach (['/admin', '/admin/schools/3/edit', '/login', '/logout', '/auth/otp/request', '/auth/magic'] as $path) {
            self::assertTrue(SessionRoutes::requiresSession($path), $path);
            self::assertSame('admin', SessionRoutes::area($path), $path);
        }

        foreach (['/submit' => '/submit', '/submit/scan' => '/submit', '/my-report' => '/my-report', '/my-report/evidence/4' => '/my-report', '/share' => '/share', '/share/files/2' => '/share'] as $path => $area) {
            self::assertTrue(SessionRoutes::requiresSession($path), $path);
            self::assertSame($area, SessionRoutes::area($path), $path);
        }

        foreach (['/', '/schools', '/schools/ny/some-school', '/resources/get-help', '/states/ny', '/methodology', '/sitemap.xml', '/llms.txt', '/administrators', '/authors', '/login-help', '/share-card.png', '/submitted', '/my-reports', '/shared'] as $path) {
            self::assertFalse(SessionRoutes::requiresSession($path), $path);
        }
    }

    /**
     * The guard: a route that needs a session (auth, CSRF or the survivor
     * session) must live under a SessionRoutes prefix, or it would run
     * without one; and no route outside those prefixes may use session
     * middleware.
     */
    public function testEveryRouteAgreesWithSessionRoutes(): void
    {
        $router = new Router();
        require dirname(__DIR__, 2) . '/routes/web.php';

        foreach ($router->registeredRoutes() as $route) {
            $usesSession = array_intersect($route['middleware'], [AuthMiddleware::class, CsrfMiddleware::class, SurvivorSessionMiddleware::class]) !== [];
            $path = preg_replace('/\{[a-z_]+\}/i', 'x', $route['uri']);

            self::assertSame(
                $usesSession,
                SessionRoutes::requiresSession($path),
                "{$route['method']} {$route['uri']}: middleware and SessionRoutes disagree"
            );
        }
    }

    /** Each survivor area's cookie is sent only to its own pages, and only while submissions are open. */
    public function testSurvivorSessionsUseTheirOwnCookiePath(): void
    {
        $_ENV['SUBMISSIONS_ENABLED'] = 'false';
        self::assertNull(SessionRoutes::sessionOptions('/my-report', '/base'), 'no session while submissions are off');
        self::assertSame([], SessionRoutes::sessionOptions('/admin', '/base'));
        self::assertNull(SessionRoutes::sessionOptions('/schools', '/base'));

        if (!function_exists('sodium_crypto_secretstream_xchacha20poly1305_init_push')) {
            self::markTestIncomplete('The sodium extension is needed for the rest of this test.');
        }

        $_ENV['SUBMISSIONS_ENABLED'] = 'true';
        $_ENV['VAULT_MASTER_KEY'] = base64_encode(random_bytes(32));

        try {
            $options = SessionRoutes::sessionOptions('/my-report/evidence/3', '/base');
            self::assertSame('sid', $options['name']);
            self::assertSame('/my-report', $options['path']);
            self::assertSame(1800, $options['idle_seconds']);
            self::assertSame('/base/storage/sessions', $options['save_path']);
            self::assertSame('/share', SessionRoutes::sessionOptions('/share', '/base')['path']);
        } finally {
            unset($_ENV['SUBMISSIONS_ENABLED'], $_ENV['VAULT_MASTER_KEY']);
        }
    }
}
