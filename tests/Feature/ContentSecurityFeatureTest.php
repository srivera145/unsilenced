<?php

declare(strict_types=1);

namespace Tests\Feature;

use Keel\App\Models\School;
use Tests\TestCase;

/**
 * The Content-Security-Policy allows scripts and styles from this origin only
 * (no 'unsafe-inline'), so a browser ignores any inline script, inline event
 * handler, style attribute or <style> element. This checks no page relies on
 * one, so nothing silently stops working: every public page, the error page,
 * the sign-in page and every admin page.
 */
class ContentSecurityFeatureTest extends TestCase
{
    private const PUBLIC_PAGES = [
        '/',
        '/schools',
        '/schools?q=fixture',
        '/schools/ny',
        '/schools/ny/fixture-state-university',
        '/schools/tx/fixture-college-of-the-arts',
        '/resources',
        '/resources/get-help',
        '/states',
        '/states/ny',
        '/methodology',
        '/corrections',
        '/no-such-page',
        '/login',
    ];

    private const ADMIN_PAGES = [
        '/admin',
        '/admin/schools',
        '/admin/schools/new',
        '/admin/accountability',
        '/admin/accountability/new',
        '/admin/resources',
        '/admin/resources/1/edit',
        '/admin/states',
        '/admin/states/1/edit',
        '/admin/imports',
    ];

    private function assertCspSafe(string $path, string $html): void
    {
        preg_match_all('#<script\b([^>]*)>#i', $html, $scripts);
        foreach ($scripts[1] as $attributes) {
            if (preg_match('#\btype\s*=\s*["\']application/ld\+json["\']#i', $attributes)) {
                continue; // JSON-LD is data; CSP does not apply to it.
            }

            self::assertMatchesRegularExpression('#\bsrc\s*=\s*"([^"]+)"#', $attributes, "{$path} has an inline <script>");
            preg_match('#\bsrc\s*=\s*"([^"]+)"#', $attributes, $src);
            $file = self::$basePath . '/public_html' . parse_url(html_entity_decode($src[1]), PHP_URL_PATH);
            self::assertFileExists($file, "{$path} loads {$src[1]}");
        }

        self::assertDoesNotMatchRegularExpression('#<[a-z][^>]*\sstyle\s*=#i', $html, "{$path} has a style attribute");
        self::assertDoesNotMatchRegularExpression('#<style\b#i', $html, "{$path} has a <style> element");
        self::assertDoesNotMatchRegularExpression('#<[a-z][^>]*\son[a-z]+\s*=#i', $html, "{$path} has an inline event handler");
        self::assertStringNotContainsStringIgnoringCase('javascript:', $html, $path);
    }

    public function testPublicPagesUseNoInlineScriptOrStyle(): void
    {
        $this->importFixtures();

        foreach (self::PUBLIC_PAGES as $path) {
            $this->assertCspSafe($path, $this->get($path)->body);
        }
    }

    public function testAdminPagesUseNoInlineScriptOrStyle(): void
    {
        $this->importFixtures();
        $this->actingAsAdmin(['theme_preference' => 'dark']);

        $school = School::findByUnitid(990001);
        foreach ([...self::ADMIN_PAGES, '/admin/schools/' . $school['id'] . '/edit'] as $path) {
            $response = $this->get($path);
            self::assertSame(200, $response->status, $path);
            $this->assertCspSafe($path, $response->body);
        }

        // The saved theme reaches the page through a meta tag that
        // public_html/js/admin-theme.js reads before first paint.
        $admin = $this->get('/admin')->body;
        self::assertStringContainsString('<meta name="keel-theme" content="dark">', $admin);
        self::assertMatchesRegularExpression('#<meta name="keel-theme"[^>]*>\s*(<\?php.*?\?>\s*)?<script src="/js/admin-theme\.js\?v=\d+"></script>#s', $admin);
    }

    /** Phase 2: every survivor page and every admin moderation page. */
    public function testSurvivorAndModerationPagesUseNoInlineScriptOrStyle(): void
    {
        $this->importFixtures();
        $pages = ['/submit (coming soon)' => $this->get('/submit')->body];

        $this->setEnv('SUBMISSIONS_ENABLED', 'true');
        \Keel\App\Support\Config::set('survivor_reports.proof_of_work_bits', 4);

        $this->get('/submit');
        $challenge = \Keel\App\Services\Survivor\ProofOfWork::challenge();
        $photo = \Tests\Support\EvidenceFixtures::write(\Tests\Support\EvidenceFixtures::jpegWithGps(), '.jpg');
        $school = School::findByUnitid(990001);
        $answers = [
            '_csrf' => $this->csrfToken(), 'pow_challenge' => $challenge['challenge'],
            'pow_nonce' => \Keel\App\Services\Survivor\ProofOfWork::solve($challenge['challenge'], $challenge['bits']),
            'school_id' => (string) $school['id'], 'incident_year' => '2023', 'perpetrator' => 'coach', 'reported_to_school' => 'no',
            'account' => 'Something happened after practice.', 'consent' => 'stats_and_account', 'attest' => '1', 'evidence_attest' => '1',
        ];
        $keyPage = $this->postWithFiles('/submit', $answers, ['evidence' => [[$photo, 'photo.jpg']]]);
        $pages['/submit (key)'] = $keyPage->body;
        preg_match_all('#<li>([a-z]+)</li>#', $keyPage->body, $words);
        $key = implode(' ', $words[1]);

        $pages['/submit'] = $this->get('/submit')->body;
        $pages['/submit (errors)'] = $this->post('/submit', ['_csrf' => 'x', 'account' => 'Tyler Smith'])->body;
        $pages['/my-report (key)'] = $this->get('/my-report')->body;
        $this->post('/my-report', ['_csrf' => $this->csrfToken(), 'case_key' => $key]);
        $pages['/my-report'] = $this->get('/my-report')->body;
        $pages['/my-report/edit'] = $this->get('/my-report/edit')->body;
        $pages['/my-report/withdraw'] = $this->get('/my-report/withdraw')->body;
        $fileId = (int) \Keel\Core\Database::connection()->query('SELECT id FROM evidence_files')->fetchColumn();
        $created = $this->post('/my-report/share-links', ['_csrf' => $this->csrfToken(), 'files' => [$fileId], 'expiry' => '24h', 'passcode' => 'secret-code']);
        $pages['/my-report/share-links'] = $created->body;
        preg_match('#/share\#([A-Za-z0-9_-]{43})"#', $created->body, $token);
        $pages['/share'] = $this->get('/share')->body;
        $pages['/share (passcode)'] = $this->post('/share/open', ['_csrf' => $this->csrfToken(), 'token' => $token[1]])->body;
        $this->post('/share/open', ['_csrf' => $this->csrfToken(), 'token' => $token[1], 'passcode' => 'secret-code']);
        $pages['/share/files'] = $this->get('/share/files')->body;
        $pages['/share (unavailable)'] = $this->post('/share/open', ['_csrf' => $this->csrfToken(), 'token' => 'nope'])->body;
        $pages['/schools/ny/fixture-state-university'] = $this->get('/schools/ny/fixture-state-university')->body;

        $this->actingAsAdmin();
        $reportId = (int) \Keel\Core\Database::connection()->query('SELECT id FROM survivor_reports')->fetchColumn();
        \Keel\Core\Session::put(\Keel\App\Middleware\FreshOtpMiddleware::SESSION_KEY, time());
        foreach (['/admin/reports', '/admin/reports?status=approved', "/admin/reports/{$reportId}", "/admin/reports/{$reportId}/evidence/{$fileId}", '/admin/verify', '/admin/illegal-content'] as $path) {
            $response = $this->get($path);
            self::assertSame(200, $response->status, $path);
            $pages[$path] = $response->body;
        }

        $blocklist = (array) \Keel\App\Support\Config::get('neutral_title_blocklist');
        foreach ($pages as $path => $html) {
            $this->assertCspSafe($path, $html);

            // A repeated id breaks label/aria references and scripts (a section
            // and a file input once shared id="evidence").
            preg_match_all('#\sid="([^"]+)"#', $html, $ids);
            $repeated = array_keys(array_filter(array_count_values($ids[1]), static fn (int $count): bool => $count > 1));
            self::assertSame([], $repeated, "{$path} repeats an id");

            preg_match('#<title>(.*?)</title>#s', $html, $title);
            foreach ($blocklist as $word) {
                self::assertStringNotContainsStringIgnoringCase($word, html_entity_decode($title[1] ?? ''), "{$path} title");
            }
            self::assertStringContainsString('id="quick-exit"', $html, $path);
        }
    }

    public function testDeckIconSpriteIsPassedAsADataAttribute(): void
    {
        $html = $this->get('/methodology')->body;

        self::assertMatchesRegularExpression('#<script src="/deck/deck\.js\?v=\d+" data-deck-icons="/deck/deck-icons\.svg\?v=\d+" defer></script>#', $html);
        self::assertStringNotContainsString('Deck.iconSprite', $html);
    }
}
