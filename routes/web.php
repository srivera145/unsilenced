<?php

use Keel\App\Controllers\Admin\AccountabilityItemController;
use Keel\App\Controllers\Admin\DashboardController as AdminDashboardController;
use Keel\App\Controllers\Admin\ImportRunController;
use Keel\App\Controllers\Admin\ResourcePageController;
use Keel\App\Controllers\Admin\SchoolController as AdminSchoolController;
use Keel\App\Controllers\Admin\StatePageController;
use Keel\App\Controllers\AuthController;
use Keel\App\Controllers\HealthController;
use Keel\App\Controllers\HomeController;
use Keel\App\Controllers\LlmsTxtController;
use Keel\App\Controllers\ResourceController;
use Keel\App\Controllers\RobotsController;
use Keel\App\Controllers\SchoolController;
use Keel\App\Controllers\SitemapController;
use Keel\App\Controllers\StateController;
use Keel\App\Controllers\ThemeController;
use Keel\App\Middleware\AuthMiddleware;
use Keel\App\Middleware\CsrfMiddleware;
use Keel\App\Middleware\RequireAdminMiddleware;
use Keel\App\Middleware\ThrottleMiddleware;

/** @var \Keel\Core\Router $router */

// Unsilenced. Phase 1.1 removed the Keel starter features this site does not
// use (billing, file uploads, API tokens, multi-tenancy, onboarding, the Keel
// docs, dashboard, settings, super-admin and welcome pages) and their tables.

$router->get('/up', [HealthController::class, 'index']);
$router->get('/sitemap.xml', [SitemapController::class, 'index']);
$router->get('/robots.txt', [RobotsController::class, 'index']);
$router->get('/llms.txt', [LlmsTxtController::class, 'index']);

// --- Public ------------------------------------------------------------------
// GET only, no middleware: no session, no cookie, no throttle (the throttle
// stores the client IP), nothing written per visit. See SessionRoutes.
$router->get('/', [HomeController::class, 'index'], ['sitemap' => true]);
$router->get('/methodology', [HomeController::class, 'methodology'], ['sitemap' => true]);
$router->get('/schools', [SchoolController::class, 'index'], ['sitemap' => true]);
$router->get('/schools/{state}', [SchoolController::class, 'state']);
$router->get('/schools/{state}/{slug}', [SchoolController::class, 'show']);
$router->get('/resources', [ResourceController::class, 'index'], ['sitemap' => true]);
$router->get('/resources/{slug}', [ResourceController::class, 'show']);
$router->get('/states', [StateController::class, 'index'], ['sitemap' => true]);
$router->get('/states/{code}', [StateController::class, 'show']);

// --- Admin sign-in and panel -------------------------------------------------
// Every path here is under a SessionRoutes prefix (/login, /auth, /logout,
// /admin), so these are the only requests that start a session.
$router->group(['middleware' => [CsrfMiddleware::class]], function ($router) {
    $router->group(['middleware' => [ThrottleMiddleware::class]], function ($router) {
        $router->get('/login', [AuthController::class, 'showLogin']);
        $router->post('/auth/otp/request', [AuthController::class, 'requestOtp']);
        $router->post('/auth/otp/verify', [AuthController::class, 'verifyOtp']);
        $router->post('/auth/magic/request', [AuthController::class, 'requestMagicLink']);
        $router->get('/auth/magic', [AuthController::class, 'verifyMagicLink']);
    });

    $router->post('/logout', [AuthController::class, 'logout']);

    $router->group(['prefix' => '/admin', 'middleware' => [AuthMiddleware::class, RequireAdminMiddleware::class]], function ($router) {
        $router->get('', [AdminDashboardController::class, 'index']);
        $router->post('/theme', [ThemeController::class, 'update']);

        $router->get('/schools', [AdminSchoolController::class, 'index']);
        $router->get('/schools/new', [AdminSchoolController::class, 'create']);
        $router->post('/schools', [AdminSchoolController::class, 'store']);
        $router->get('/schools/{id}/edit', [AdminSchoolController::class, 'edit']);
        $router->post('/schools/{id}', [AdminSchoolController::class, 'update']);
        $router->post('/schools/{id}/delete', [AdminSchoolController::class, 'destroy']);

        $router->get('/accountability', [AccountabilityItemController::class, 'index']);
        $router->get('/accountability/new', [AccountabilityItemController::class, 'create']);
        $router->post('/accountability', [AccountabilityItemController::class, 'store']);
        $router->get('/accountability/{id}/edit', [AccountabilityItemController::class, 'edit']);
        $router->post('/accountability/{id}', [AccountabilityItemController::class, 'update']);
        $router->post('/accountability/{id}/delete', [AccountabilityItemController::class, 'destroy']);

        $router->get('/resources', [ResourcePageController::class, 'index']);
        $router->get('/resources/new', [ResourcePageController::class, 'create']);
        $router->post('/resources', [ResourcePageController::class, 'store']);
        $router->get('/resources/{id}/edit', [ResourcePageController::class, 'edit']);
        $router->post('/resources/{id}', [ResourcePageController::class, 'update']);
        $router->post('/resources/{id}/delete', [ResourcePageController::class, 'destroy']);

        $router->get('/states', [StatePageController::class, 'index']);
        $router->get('/states/new', [StatePageController::class, 'create']);
        $router->post('/states', [StatePageController::class, 'store']);
        $router->get('/states/{id}/edit', [StatePageController::class, 'edit']);
        $router->post('/states/{id}', [StatePageController::class, 'update']);
        $router->post('/states/{id}/delete', [StatePageController::class, 'destroy']);

        $router->get('/imports', [ImportRunController::class, 'index']);
        $router->get('/imports/{id}', [ImportRunController::class, 'show']);
    });
});
