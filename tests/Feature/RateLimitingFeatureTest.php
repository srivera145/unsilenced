<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class RateLimitingFeatureTest extends TestCase
{
    public function testThrottleBlocksAfterLimitIsExceeded(): void
    {
        $headers = [
            'X-CSRF-Token' => $this->csrfToken(),
            'Accept' => 'application/json',
        ];

        $blocked = false;

        for ($index = 1; $index <= 35; $index++) {
            $response = $this->postJson('/auth/otp/request', [
                'email' => 'ratelimit' . $index . '@example.test',
            ], $headers);

            if ($response->status === 429) {
                $blocked = true;
                break;
            }
        }

        self::assertTrue($blocked, 'Expected throttle middleware to block requests after exceeding the rate limit.');
    }

    public function testExpiredEntriesAndTheirIpAddressesAreDeleted(): void
    {
        $connection = \Keel\Core\Database::connection();
        $connection->exec(
            "INSERT INTO rate_limits (`key`, attempts, expires_at) VALUES
                ('198.51.100.7|/auth/otp/request', 3, DATE_SUB(NOW(), INTERVAL 5 MINUTE)),
                ('198.51.100.8|/login', 1, DATE_ADD(NOW(), INTERVAL 1 MINUTE))"
        );

        self::assertTrue(\Keel\Core\RateLimiter::attempt('203.0.113.9|/login', 30, 1));

        $keys = $connection->query('SELECT `key` FROM rate_limits ORDER BY `key`')->fetchAll(\PDO::FETCH_COLUMN);
        self::assertSame(['198.51.100.8|/login', '203.0.113.9|/login'], $keys, 'the expired entry is gone; current ones stay');
    }
}
