<?php

declare(strict_types=1);

namespace Tests\Feature;

use Keel\Core\CapturedResponseException;
use Keel\Core\ErrorHandler;
use Tests\TestCase;

/**
 * An uncaught exception on a public page, end to end: the 500 page renders and
 * the line that reaches the error log carries the error, not the visitor.
 */
class ErrorLogPrivacyFeatureTest extends TestCase
{
    private string $logFile;
    private string|false $previousLog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logFile = tempnam(sys_get_temp_dir(), 'unsilenced-error-log-');
        $this->previousLog = ini_get('error_log');
        ini_set('error_log', $this->logFile);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', (string) $this->previousLog);
        @unlink($this->logFile);
        parent::tearDown();
    }

    public function testTheErrorLogGetsTheErrorButNotTheQueryStringOrIp(): void
    {
        $_SERVER['REMOTE_ADDR'] = '198.51.100.23';
        $_SERVER['REQUEST_URI'] = '/schools?q=my+school';
        $_SERVER['QUERY_STRING'] = 'q=my+school';

        try {
            ErrorHandler::render(500, new \RuntimeException('Lookup failed for /schools?q=my+school (client 198.51.100.23)'));
            self::fail('render() should hand the page back under the test harness.');
        } catch (CapturedResponseException $response) {
            self::assertSame(500, $response->status);
        }

        $log = (string) file_get_contents($this->logFile);

        self::assertStringContainsString('[Keel] Uncaught RuntimeException: Lookup failed for /schools?[query removed]', $log);
        self::assertStringNotContainsString('my+school', $log);
        self::assertStringNotContainsString('q=', $log);
        self::assertStringNotContainsString('198.51.100.23', $log);
    }
}
