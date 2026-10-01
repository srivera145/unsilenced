<?php

use Keel\App\Controllers\Admin\AccountabilityItemController;
use Keel\App\Controllers\Admin\DashboardController as AdminDashboardController;
use Keel\App\Controllers\Admin\ImportRunController;
use Keel\App\Controllers\Admin\ModerationController;
use Keel\App\Controllers\MyReportController;
use Keel\App\Controllers\ShareLinkController;
use Keel\App\Controllers\SubmitController;
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
use Keel\App\Middleware\FreshOtpMiddleware;
use Keel\App\Middleware\RequireAdminMiddleware;
use Keel\App\Middleware\SubmissionsEnabledMiddleware;
use Keel\App\Middleware\SurvivorSessionMiddleware;
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
$router->get('/corrections', [HomeController::class, 'corrections'], ['sitemap' => true]);
$router->get('/schools', [SchoolController::class, 'index'], ['sitemap' => true]);
$router->get('/schools/{state}', [SchoolController::class, 'state']);
$router->get('/schools/{state}/{slug}', [SchoolController::class, 'show']);
$router->get('/resources', [ResourceController::class, 'index'], ['sitemap' => true]);
$router->get('/resources/{slug}', [ResourceController::class, 'show']);
$router->get('/states', [StateController::class, 'index'], ['sitemap' => true]);
$router->get('/states/{code}', [StateController::class, 'show']);

// --- Survivor reports (Phase 2) ------------------------------------------------
// SUBMISSIONS_ENABLED=false (the default): every path here is the "coming
// soon" page, with no session. On: each area has its own session cookie
// (SessionRoutes), every page is no-store, and the session ends after 30
// idle minutes. No throttle middleware: it would store IP addresses.
$router->group(['middleware' => [SubmissionsEnabledMiddleware::class, SurvivorSessionMiddleware::class]], function ($router) {
    // The form keeps everything in the page until the final submit. That POST
    // checks its own CSRF token so a timed-out session re-shows her answers
    // instead of a bare "page expired".
    $router->get('/submit', [SubmitController::class, 'show']);
    $router->get('/submit/schools', [SubmitController::class, 'schools']);
    $router->post('/submit', [SubmitController::class, 'store']);

    $router->group(['middleware' => [CsrfMiddleware::class]], function ($router) {
        $router->post('/submit/scan', [SubmitController::class, 'scan']);

        $router->get('/my-report', [MyReportController::class, 'show']);
        $router->post('/my-report', [MyReportController::class, 'open']);
        $router->post('/my-report/sign-out', [MyReportController::class, 'signOut']);
        $router->get('/my-report/edit', [MyReportController::class, 'edit']);
        $router->get('/my-report/schools', [SubmitController::class, 'schools']);
        $router->post('/my-report/edit', [MyReportController::class, 'update']);
        $router->post('/my-report/consent', [MyReportController::class, 'lowerConsent']);
        $router->post('/my-report/email', [MyReportController::class, 'email']);
        $router->post('/my-report/evidence', [MyReportController::class, 'addEvidence']);
        $router->get('/my-report/evidence/{id}', [MyReportController::class, 'viewEvidence']);
        $router->post('/my-report/evidence/{id}/delete', [MyReportController::class, 'deleteEvidence']);
        $router->post('/my-report/share-links', [MyReportController::class, 'createShareLink']);
        $router->post('/my-report/share-links/{id}/revoke', [MyReportController::class, 'revokeShareLink']);
        $router->get('/my-report/withdraw', [MyReportController::class, 'withdraw']);
        $router->post('/my-report/withdraw', [MyReportController::class, 'confirmWithdraw']);
        $router->post('/my-report/withdraw/confirm', [MyReportController::class, 'destroy']);

        // The token is in the URL fragment, which never reaches the server:
        // /share's script (or the paste-your-link form) posts it to /share/open.
        $router->get('/share', [ShareLinkController::class, 'show']);
        $router->post('/share/open', [ShareLinkController::class, 'open']);
        $router->get('/share/files', [ShareLinkController::class, 'files']);
        $router->get('/share/files/{id}', [ShareLinkController::class, 'download']);
        $router->get('/share/download', [ShareLinkController::class, 'downloadAll']);
        $router->post('/share/close', [ShareLinkController::class, 'close']);
    });
});

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

        // Survivor reports. Works whatever SUBMISSIONS_ENABLED says, for testing.
        $router->get('/reports', [ModerationController::class, 'index']);
        $router->get('/reports/{id}', [ModerationController::class, 'show']);
        $router->post('/reports/{id}/start-review', [ModerationController::class, 'startReview']);
        $router->post('/reports/{id}/published', [ModerationController::class, 'savePublished']);
        $router->post('/reports/{id}/note', [ModerationController::class, 'saveNote']);
        $router->post('/reports/{id}/request-changes', [ModerationController::class, 'requestChanges']);
        $router->post('/reports/{id}/reject', [ModerationController::class, 'reject']);
        $router->post('/reports/{id}/approve', [ModerationController::class, 'approve']);
        $router->post('/reports/{id}/evidence/{fileId}/quarantine', [ModerationController::class, 'quarantine']);
        $router->get('/illegal-content', [ModerationController::class, 'illegalContent']);

        // Viewing evidence needs a code entered in the last 15 minutes.
        $router->get('/verify', [ModerationController::class, 'showVerify']);
        $router->post('/verify/send', [ModerationController::class, 'sendVerifyCode']);
        $router->post('/verify', [ModerationController::class, 'verify']);
        $router->group(['middleware' => [FreshOtpMiddleware::class]], function ($router) {
            $router->get('/reports/{id}/evidence/{fileId}', [ModerationController::class, 'viewEvidence']);
            $router->get('/reports/{id}/evidence/{fileId}/file', [ModerationController::class, 'evidenceFile']);
        });
    });
});
