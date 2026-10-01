<?php

declare(strict_types=1);

namespace Tests\Feature;

use Keel\App\Support\SessionRoutes;
use Keel\Core\Database;
use Keel\Core\Session;
use Tests\Support\EvidenceFixtures;
use Tests\Support\SurvivorHelpers;
use Tests\TestCase;

/**
 * The promises around a survivor's visit: no IP address stored, no case
 * key, account text, file name or share token in any log, and a session
 * cookie only where it is needed, locked down.
 */
class SurvivorPrivacyFeatureTest extends TestCase
{
    use SurvivorHelpers;

    private const VISITOR_IP = '203.0.113.71';

    protected function setUp(): void
    {
        parent::setUp();
        $this->importFixtures();
        $this->enableSubmissions();
    }

    public function testAWholeVisitLeavesNoKeyTextNameTokenOrAddressInAnyLog(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'applog');
        $previousLog = ini_set('error_log', $log);
        $_SERVER['REMOTE_ADDR'] = self::VISITOR_IP;
        $account = 'The account text that must never be logged: Pinecrest incident.';

        try {
            ['key' => $key] = $this->submitReport(['account' => $account, 'email' => 'private.person@example.org'], [
                [EvidenceFixtures::write(EvidenceFixtures::jpegWithGps(), '.jpg'), 'secret-file-name.jpg'],
            ]);
            $this->openReport((string) $key);
            $this->get('/my-report');
            $fileId = (int) Database::connection()->query('SELECT id FROM evidence_files')->fetchColumn();
            $created = $this->post('/my-report/share-links', ['_csrf' => $this->csrfToken(), 'files' => [$fileId], 'expiry' => '24h']);
            preg_match('#/share\#([A-Za-z0-9_-]{43})"#', $created->body, $token);
            $this->post('/share/open', ['_csrf' => $this->csrfToken(), 'token' => $token[1]]);
            $this->get('/share/files');
            $this->get('/share/download');
            $this->post('/my-report/withdraw', ['_csrf' => $this->csrfToken()]);
            // Errors on purpose: a wrong key, a bad token, a file that is not allowed.
            $this->openReport('wrong words that open nothing at all');
            $this->post('/share/open', ['_csrf' => $this->csrfToken(), 'token' => strrev($token[1])]);
            $this->postWithFiles('/my-report/evidence', ['_csrf' => $this->csrfToken(), 'evidence_attest' => '1'], ['evidence' => [[EvidenceFixtures::write("\x00binary", '.bin'), 'another-secret-name.exe']]]);
        } finally {
            ini_set('error_log', (string) $previousLog);
        }

        $logs = (string) file_get_contents($log) . $this->latestMailLog();
        @unlink($log);

        foreach ([$key, $account, 'Pinecrest', 'secret-file-name', 'another-secret-name', $token[1], self::VISITOR_IP] as $secret) {
            self::assertStringNotContainsString((string) $secret, $logs, 'logged: ' . $secret);
        }
        foreach (explode(' ', (string) $key) as $word) {
            self::assertStringNotContainsString(' ' . $word . ' ', $logs);
        }

        // Nothing about the visitor in the database either.
        self::assertSame(0, (int) Database::connection()->query('SELECT COUNT(*) FROM activity_log')->fetchColumn(), 'survivor actions never touch the activity log (it stores IPs)');
        self::assertSame(['survivor-submissions'], Database::connection()->query('SELECT `key` FROM rate_limits')->fetchAll(\PDO::FETCH_COLUMN));
        $everything = json_encode(Database::connection()->query('SELECT * FROM survivor_cases')->fetchAll())
            . json_encode(Database::connection()->query('SELECT * FROM survivor_reports')->fetchAll())
            . json_encode(Database::connection()->query('SELECT * FROM moderation_events')->fetchAll())
            . json_encode(Database::connection()->query('SELECT * FROM share_links')->fetchAll());
        self::assertStringNotContainsString(self::VISITOR_IP, $everything);
        self::assertStringNotContainsString('private.person', $everything);
    }

    public function testTheSessionHoldsIdsNotSecrets(): void
    {
        ['key' => $key] = $this->submitReport();
        $this->openReport((string) $key);

        $session = serialize($_SESSION);
        self::assertStringNotContainsString((string) $key, $session);
        self::assertStringNotContainsString(self::ACCOUNT, $session);
        foreach (explode(' ', (string) $key) as $word) {
            self::assertStringNotContainsString('"' . $word . '"', $session);
        }
    }

    public function testSurvivorCookiesAreLockedDownAndScopedToTheirArea(): void
    {
        $basePath = dirname(__DIR__, 2);

        foreach (['/submit', '/my-report', '/share'] as $area) {
            $options = SessionRoutes::sessionOptions($area . '/x', $basePath);
            self::assertSame('sid', $options['name']);
            self::assertSame($area, $options['path']);
            self::assertSame(1800, $options['idle_seconds'], '30 minutes');

            $cookie = Session::cookieParams(true, $options['path']);
            self::assertTrue($cookie['httponly']);
            self::assertTrue($cookie['secure']);
            self::assertSame('Strict', $cookie['samesite']);
            self::assertSame(0, $cookie['lifetime'], 'gone when the browser closes');
        }

        self::assertNull(SessionRoutes::sessionOptions('/schools/ny/fixture-state-university', $basePath), 'public pages: no session, no cookie');
        self::assertNull(SessionRoutes::sessionOptions('/', $basePath));
    }

    public function testInProductionTheSurvivorPagesNeedHttps(): void
    {
        $this->setEnv('APP_ENV', 'production');

        try {
            self::assertStringContainsString('Coming soon', $this->get('/submit')->body);
            $_SERVER['HTTPS'] = 'on';
            self::assertStringContainsString('Tell us what happened', $this->get('/submit')->body);
        } finally {
            unset($_SERVER['HTTPS']);
            $this->setEnv('APP_ENV', 'testing');
        }
    }
}
