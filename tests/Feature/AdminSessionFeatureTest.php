<?php

declare(strict_types=1);

namespace Tests\Feature;

use Keel\Core\Session;
use Tests\TestCase;

/**
 * An admin who does nothing for two hours is signed out, so a panel left open
 * on a shared computer does not stay open.
 */
class AdminSessionFeatureTest extends TestCase
{
    public function testAnAdminIdleForTwoHoursIsSignedOut(): void
    {
        $this->actingAsAdmin();
        self::assertSame(200, $this->get('/admin')->status);

        Session::touch(time() - Session::IDLE_TIMEOUT_SECONDS - 1);
        $response = $this->get('/admin/schools');

        self::assertSame(302, $response->status);
        self::assertSame('/login', $response->header('Location'));
        self::assertFalse(Session::has('user_id'), 'the session is cleared, not just redirected');
    }

    public function testActivityWithinTwoHoursKeepsTheAdminSignedInAndResetsTheClock(): void
    {
        $this->actingAsAdmin();

        Session::touch(time() - Session::IDLE_TIMEOUT_SECONDS + 60);
        self::assertSame(200, $this->get('/admin')->status);

        self::assertFalse(Session::isIdleExpired(), 'the request recorded new activity');
        self::assertTrue(Session::isIdleExpired(time() + Session::IDLE_TIMEOUT_SECONDS + 1));
    }

    public function testASessionWithNoRecordedActivityIsSignedOut(): void
    {
        $this->actingAsAdmin();
        unset($_SESSION['last_activity_at']);

        self::assertSame(302, $this->get('/admin')->status);
    }
}
