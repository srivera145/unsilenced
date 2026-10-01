<?php

declare(strict_types=1);

namespace Tests\Unit;

use Keel\App\Middleware\AuthMiddleware;
use Keel\App\Middleware\CsrfMiddleware;
use Keel\App\Support\SessionRoutes;
use Keel\Core\Router;
use PHPUnit\Framework\TestCase;

class SessionRoutesTest extends TestCase
{
    public function testOnlyAdminAndSignInPathsStartASession(): void
    {
        foreach (['/admin', '/admin/schools/3/edit', '/login', '/logout', '/auth/otp/request', '/auth/magic'] as $path) {
            self::assertTrue(SessionRoutes::requiresSession($path), $path);
        }

        foreach (['/', '/schools', '/schools/ny/some-school', '/resources/get-help', '/states/ny', '/methodology', '/sitemap.xml', '/llms.txt', '/administrators', '/authors', '/login-help'] as $path) {
            self::assertFalse(SessionRoutes::requiresSession($path), $path);
        }
    }

    /**
     * The guard: a route that needs a session (auth or CSRF) must live under a
     * SessionRoutes prefix, or it would run without one; and no route outside
     * those prefixes may use session middleware.
     */
    public function testEveryRouteAgreesWithSessionRoutes(): void
    {
        $router = new Router();
        require dirname(__DIR__, 2) . '/routes/web.php';

        foreach ($router->registeredRoutes() as $route) {
            $usesSession = array_intersect($route['middleware'], [AuthMiddleware::class, CsrfMiddleware::class]) !== [];
            $path = preg_replace('/\{[a-z_]+\}/i', 'x', $route['uri']);

            self::assertSame(
                $usesSession,
                SessionRoutes::requiresSession($path),
                "{$route['method']} {$route['uri']}: middleware and SessionRoutes disagree"
            );
        }
    }
}
