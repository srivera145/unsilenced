<?php

declare(strict_types=1);

namespace Tests\Unit;

use Keel\Core\ErrorHandler;
use PHPUnit\Framework\TestCase;

/**
 * What ErrorHandler writes to the error log: never the request's query string
 * or the client's address, even when the exception message quotes them.
 */
class ErrorHandlerTest extends TestCase
{
    private array $serverBackup = [];

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
        $_SERVER['REQUEST_URI'] = '/auth/magic?token=abc123secret&email=someone%40example.org';
        $_SERVER['QUERY_STRING'] = 'token=abc123secret&email=someone%40example.org';
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
    }

    public function testLogLineKeepsTheErrorButDropsQueryStringsAndAddresses(): void
    {
        $e = new \RuntimeException(
            'Bad request /auth/magic?token=abc123secret&email=someone%40example.org from 203.0.113.9, '
            . 'proxied for 198.51.100.4 and 2001:db8::7; search /schools?q=state+university failed'
        );

        $line = ErrorHandler::logLine($e);

        self::assertStringStartsWith('[Keel] Uncaught RuntimeException: Bad request /auth/magic?[query removed]', $line);
        self::assertStringContainsString('/schools?[query removed] failed', $line);
        self::assertStringContainsString(__FILE__ . ':', $line);
        foreach (['abc123secret', 'someone', 'token=', 'state+university', '203.0.113.9', '198.51.100.4', '2001:db8::7'] as $leak) {
            self::assertStringNotContainsString($leak, $line);
        }
    }

    public function testTheCurrentRequestsValuesAreRemovedWhereverTheyAppear(): void
    {
        $_SERVER['QUERY_STRING'] = 'q=fixture';
        $_SERVER['REMOTE_ADDR'] = '::1';

        $scrubbed = ErrorHandler::scrub('query was q=fixture, client ::1');

        self::assertSame('query was [query removed], client [ip removed]', $scrubbed);
    }

    public function testOrdinaryMessagesAreLeftAlone(): void
    {
        $_SERVER = [];

        foreach ([
            'Call to undefined method Keel\App\Models\School::fooBar()',
            'View not found: errors.500',
            'SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry',
            'Is this right?',
            'Expected 12:30:45 to be a time',
        ] as $message) {
            self::assertSame($message, ErrorHandler::scrub($message));
        }
    }
}
