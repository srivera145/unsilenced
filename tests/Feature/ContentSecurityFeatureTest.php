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

    public function testDeckIconSpriteIsPassedAsADataAttribute(): void
    {
        $html = $this->get('/methodology')->body;

        self::assertMatchesRegularExpression('#<script src="/deck/deck\.js\?v=\d+" data-deck-icons="/deck/deck-icons\.svg\?v=\d+" defer></script>#', $html);
        self::assertStringNotContainsString('Deck.iconSprite', $html);
    }
}
