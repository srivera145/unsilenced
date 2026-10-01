<?php

declare(strict_types=1);

namespace Tests\Feature;

use Keel\Core\Database;
use Tests\Support\SurvivorHelpers;
use Tests\TestCase;

/**
 * SUBMISSIONS_ENABLED=false (the default until legal review): every survivor
 * route is the "coming soon" page with the hotline, stores nothing, and the
 * school-page section is hidden. The admin queue still works.
 */
class SubmissionsFlagFeatureTest extends TestCase
{
    use SurvivorHelpers;

    private const SURVIVOR_GETS = ['/submit', '/submit/schools?q=fixture', '/my-report', '/my-report/edit', '/my-report/evidence/1', '/my-report/withdraw', '/share', '/share/files', '/share/download'];
    private const SURVIVOR_POSTS = ['/submit', '/submit/scan', '/my-report', '/my-report/edit', '/my-report/evidence', '/my-report/share-links', '/my-report/withdraw/confirm', '/share/open', '/share/close'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importFixtures();
    }

    public function testEverySurvivorRouteShowsComingSoonWithTheHotline(): void
    {
        foreach (self::SURVIVOR_GETS as $path) {
            $response = $this->get($path);
            self::assertSame(200, $response->status, $path);
            self::assertStringContainsString('Coming soon', $response->body, $path);
            self::assertStringContainsString('1-800-656-4673', $response->body, $path);
            self::assertStringContainsString('id="quick-exit"', $response->body, $path);
            self::assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $response->body, $path);
        }

        foreach (self::SURVIVOR_POSTS as $path) {
            $response = $this->post($path, $this->answers() + ['case_key' => 'a b c d e f', 'token' => str_repeat('a', 43)]);
            self::assertSame(200, $response->status, $path);
            self::assertStringContainsString('Coming soon', $response->body, $path);
        }

        foreach (['survivor_cases', 'survivor_reports', 'evidence_files', 'share_links', 'moderation_events', 'rate_limits'] as $table) {
            self::assertSame(0, (int) Database::connection()->query("SELECT COUNT(*) FROM {$table}")->fetchColumn(), $table);
        }
    }

    public function testTheSchoolPageAndHomeShowNothingOfIt(): void
    {
        $this->makeReport([], 'approved');

        $school = $this->get('/schools/ny/fixture-state-university')->body;
        self::assertStringNotContainsString('What survivors have told us', $school);
        self::assertStringNotContainsString('/submit', $school);
        self::assertStringNotContainsString('/submit', $this->get('/')->body);
    }

    public function testEnabledWithoutAValidVaultKeyStaysClosed(): void
    {
        $this->enableSubmissions();
        $this->setEnv('VAULT_MASTER_KEY', 'too-short');

        self::assertStringContainsString('Coming soon', $this->get('/submit')->body);

        $this->setEnv('VAULT_MASTER_KEY', (string) self::$vaultKey);
        self::assertStringContainsString('Tell us what happened', $this->get('/submit')->body);
    }

    public function testTheAdminQueueWorksWithSubmissionsOff(): void
    {
        $this->actingAsAdmin();
        $report = $this->makeReport();

        $queue = $this->get('/admin/reports');
        self::assertSame(200, $queue->status);
        self::assertStringContainsString('Submissions are switched off', $queue->body);
        self::assertStringContainsString('/admin/reports/' . $report['report_id'], $queue->body);
        self::assertSame(200, $this->get('/admin/reports/' . $report['report_id'])->status);
    }
}
