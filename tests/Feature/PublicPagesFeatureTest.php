<?php

declare(strict_types=1);

namespace Tests\Feature;

use Keel\Core\Database;
use Tests\TestCase;

/**
 * The safety promises every public page makes: no outside resources, a quick
 * exit, the hotline, a neutral title, and nothing about the visitor stored.
 */
class PublicPagesFeatureTest extends TestCase
{
    private const PUBLIC_PAGES = [
        '/',
        '/schools',
        '/schools?q=fixture',
        '/schools/ny',
        '/schools/ny/fixture-state-university',
        '/schools/ny/fixture-polytechnic-institute',
        '/schools/tx/fixture-college-of-the-arts',
        '/resources',
        '/resources/get-help',
        '/resources/your-options',
        '/resources/save-evidence',
        '/states',
        '/states/ny',
        '/methodology',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importFixtures();
    }

    public function testEveryPublicPageRendersWithQuickExitAndHotline(): void
    {
        foreach (self::PUBLIC_PAGES as $path) {
            $response = $this->get($path);

            self::assertSame(200, $response->status, $path);
            self::assertStringContainsString('id="quick-exit"', $response->body, $path);
            self::assertStringContainsString('href="https://weather.com/"', $response->body, $path);
            self::assertStringContainsString('window.location.replace(exitUrl)', $response->body, $path);
            self::assertStringContainsString("event.key !== 'Escape'", $response->body, $path);
            self::assertStringContainsString('1-800-656-4673', $response->body, $path);
            self::assertStringContainsString('<meta name="description"', $response->body, $path);
        }
    }

    public function testNoPublicPageReferencesAnyExternalScriptStylesheetFontOrFrame(): void
    {
        foreach (array_merge(self::PUBLIC_PAGES, ['/no-such-page']) as $path) {
            $html = $this->get($path)->body;

            preg_match_all('#<script\b[^>]*\bsrc\s*=\s*["\']?([^"\'\s>]+)#i', $html, $scripts);
            preg_match_all('#<link\b[^>]*\bhref\s*=\s*["\']?([^"\'\s>]+)#i', $html, $links);
            preg_match_all('#<(?:img|iframe|source|video|audio|embed|object)\b[^>]*\b(?:src|data)\s*=\s*["\']?([^"\'\s>]+)#i', $html, $media);
            preg_match_all('#(?:@import|url\()\s*["\']?(https?:)?//#i', $html, $cssUrls);

            foreach (array_merge($scripts[1], $media[1]) as $url) {
                self::assertDoesNotMatchRegularExpression('#^(https?:)?//#i', $url, "{$path} loads {$url}");
            }

            foreach ($links[1] as $index => $url) {
                // <link rel="canonical"> legitimately points at this site's own absolute URL.
                $tag = $links[0][$index];
                if (preg_match('#rel=["\']canonical#i', $tag)) {
                    continue;
                }
                self::assertDoesNotMatchRegularExpression('#^(https?:)?//#i', $url, "{$path} links {$tag}");
            }

            self::assertSame([], $cssUrls[0], "{$path} imports CSS or fonts from a URL");
            self::assertStringNotContainsString('fonts.googleapis', $html);
            self::assertStringNotContainsString('<iframe', $html);
        }
    }

    public function testTitlesAreNeutral(): void
    {
        foreach (array_merge(self::PUBLIC_PAGES, ['/no-such-page', '/login']) as $path) {
            preg_match('#<title>(.*?)</title>#s', $this->get($path)->body, $match);
            $title = strtolower(html_entity_decode($match[1] ?? ''));

            self::assertStringContainsString('unsilenced', $title, $path);
            foreach ((array) \Keel\App\Support\Config::get('neutral_title_blocklist') as $word) {
                self::assertStringNotContainsString($word, $title, "{$path} title contains '{$word}'");
            }
        }

        self::assertStringContainsString('<title>Unsilenced</title>', $this->get('/')->body);

        // The evidence page keeps its descriptive heading but a neutral tab title.
        $evidence = $this->get('/resources/save-evidence')->body;
        self::assertStringContainsString('<title>Keeping records · Unsilenced</title>', $evidence);
        self::assertStringContainsString('<h1 class="h2">Save your evidence</h1>', $evidence);
    }

    public function testAdminCannotGiveAResourcePageARevealingTabTitle(): void
    {
        $this->actingAsAdmin();

        $response = $this->post('/admin/resources', [
            '_csrf' => $this->csrfToken(),
            'slug' => 'new-page',
            'title' => 'After a sexual assault',
            'summary' => 'Summary.',
            'body' => 'Body.',
        ]);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('contains &quot;sexual&quot;', $response->body);

        $fixed = $this->post('/admin/resources', [
            '_csrf' => $this->csrfToken(),
            'slug' => 'new-page',
            'title' => 'After a sexual assault',
            'browser_title' => 'Next steps',
            'summary' => 'Summary.',
            'body' => 'Body.',
        ]);

        self::assertSame(302, $fixed->status);
        \Keel\Core\Database::connection()->exec("DELETE FROM resource_pages WHERE slug = 'new-page'");
    }

    public function testHomeHasHeadlineSearchHelpAndMethodology(): void
    {
        $body = $this->get('/')->body;

        self::assertStringContainsString('class="enough">Enough.</h1>', $body);
        self::assertStringContainsString('action="/schools"', $body);
        self::assertStringContainsString('name="q"', $body);
        self::assertStringContainsString('Need help now?', $body);
        self::assertStringContainsString('href="tel:+18006564673"', $body);
        self::assertStringContainsString('href="/methodology"', $body);
    }

    public function testResourcePagesCarryRequiredGuidance(): void
    {
        $help = $this->get('/resources/get-help')->body;
        self::assertStringContainsString('https://online.rainn.org', $help);
        self::assertStringContainsString('911', $help);

        $options = $this->get('/resources/your-options')->body;
        foreach (['Medical care', 'Reporting to the police', 'Title IX', 'civil attorney', 'Confidential advocates'] as $heading) {
            self::assertStringContainsString($heading, $options);
        }

        $evidence = $this->get('/resources/save-evidence')->body;
        self::assertStringContainsString('Never upload intimate images anywhere', $evidence);
        self::assertStringContainsString('give them only to the police or to an attorney', $evidence);
        self::assertStringContainsString('screenshot', strtolower($evidence));
    }

    public function testPublicRequestsStoreNothingAboutTheVisitor(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.77';

        foreach (self::PUBLIC_PAGES as $path) {
            $this->get($path);
        }

        $connection = Database::connection();
        self::assertSame(0, (int) $connection->query('SELECT COUNT(*) FROM rate_limits')->fetchColumn());
        self::assertSame(0, (int) $connection->query('SELECT COUNT(*) FROM activity_log')->fetchColumn());

        $log = self::$basePath . '/storage/logs/app.log';
        if (is_file($log)) {
            self::assertStringNotContainsString('203.0.113.77', (string) file_get_contents($log));
        }
    }

    public function testPublicPagesEmitNoCsrfTokenOrSessionMarkup(): void
    {
        // The harness always has a session, so this checks the markup the head
        // partial would emit only for a session; SessionRoutesTest covers which
        // paths start one.
        session_write_close();

        try {
            $body = $this->get('/schools/ny/fixture-state-university')->body;
            self::assertStringNotContainsString('csrf-token', $body);
            self::assertStringNotContainsString('/js/keel.js', $body);
            self::assertStringNotContainsString('localStorage.setItem', $body);
        } finally {
            \Keel\Core\Session::start();
        }
    }
}
