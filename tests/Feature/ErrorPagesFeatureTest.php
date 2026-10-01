<?php

declare(strict_types=1);

namespace Tests\Feature;

use Keel\Core\CapturedResponseException;
use Keel\Core\ErrorHandler;
use Tests\TestCase;

/**
 * Someone can land on an error page from anywhere, so it carries everything a
 * public page does: the quick exit, the help bar, the hotline and a way home.
 * With APP_DEBUG=false it says nothing about the error itself.
 */
class ErrorPagesFeatureTest extends TestCase
{
    private string|false $previousLog;
    private string $logFile;

    protected function setUp(): void
    {
        parent::setUp();
        // The 500 tests log an exception; keep it out of storage/logs.
        $this->logFile = (string) tempnam(sys_get_temp_dir(), 'unsilenced-error-pages-');
        $this->previousLog = ini_get('error_log');
        ini_set('error_log', $this->logFile);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', (string) $this->previousLog);
        @unlink($this->logFile);
        unset($_ENV['APP_DEBUG'], $_SERVER['APP_DEBUG']);
        parent::tearDown();
    }

    private function assertSafetyFeatures(string $label, string $body): void
    {
        self::assertStringContainsString('<a class="quick-exit" id="quick-exit" href="https://weather.com/"', $body, $label);
        self::assertMatchesRegularExpression('#<script src="/js/quick-exit\.js\?v=\d+"></script>#', $body, $label);
        self::assertStringContainsString('<aside class="help-bar"', $body, $label);
        self::assertStringContainsString('Need help now?', $body, $label);
        self::assertStringContainsString('href="tel:+18006564673"', $body, $label);
        self::assertStringContainsString('Talk to someone now', $body, $label);
        self::assertStringContainsString('<a href="/" class="btn btn-primary">Home</a>', $body, $label);
        self::assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $body, $label);
        self::assertStringNotContainsString('og:image', $body, $label);
    }

    private function render500(\Throwable $exception): CapturedResponseException
    {
        try {
            ErrorHandler::render(500, $exception);
        } catch (CapturedResponseException $response) {
            return $response;
        }

        self::fail('render() should hand the page back under the test harness.');
    }

    public function testNotFoundPagesCarryQuickExitHelpAndAWayHome(): void
    {
        $this->importFixtures();

        foreach (['/no-such-page', '/schools/ny/no-such-school', '/resources/no-such-page', '/states/zz'] as $path) {
            $response = $this->get($path);
            self::assertSame(404, $response->status, $path);
            self::assertStringContainsString('Page not found', $response->body, $path);
            $this->assertSafetyFeatures($path, $response->body);
        }
    }

    public function testServerErrorPageCarriesQuickExitHelpAndAWayHomeAndNoDetails(): void
    {
        $_ENV['APP_DEBUG'] = 'false';
        $_SERVER['APP_DEBUG'] = 'false';

        $response = $this->render500(new \RuntimeException('SQLSTATE[42S02]: table unsilenced.secret_table is missing'));

        self::assertSame(500, $response->status);
        self::assertStringContainsString('Something went wrong', $response->body);
        $this->assertSafetyFeatures('500', $response->body);
        self::assertStringNotContainsString('secret_table', $response->body);
        self::assertStringNotContainsString('SQLSTATE', $response->body);
        self::assertStringNotContainsString('RuntimeException', $response->body);
        self::assertStringNotContainsString(__FILE__, $response->body);
        self::assertStringNotContainsString('.php', $response->body);
        self::assertStringNotContainsString('#0 ', $response->body, 'no stack trace');
    }

    public function testDebugModeShowsTheErrorToDevelopersOnly(): void
    {
        $_ENV['APP_DEBUG'] = 'true';
        $_SERVER['APP_DEBUG'] = 'true';

        $response = $this->render500(new \RuntimeException('a detail for developers'));

        self::assertStringContainsString('a detail for developers', $response->body);
        $this->assertSafetyFeatures('500 debug', $response->body);
    }
}
