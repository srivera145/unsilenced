<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The brand as shipped: the inline logo, the favicon set, the self-hosted
 * wordmark font, and colours that live in CSS tokens rather than in views.
 * Contrast is measured in a browser (docs/phase-1.1/CONTRAST.md), not here.
 */
class BrandFeatureTest extends TestCase
{
    private static function publicPath(string $path): string
    {
        return self::$basePath . '/public_html' . $path;
    }

    public function testPagesCarryTheInlineLogoAndFavicons(): void
    {
        $this->importFixtures();

        foreach (['/', '/schools/ny/fixture-state-university', '/no-such-page', '/login'] as $path) {
            $body = $this->get($path)->body;

            self::assertStringContainsString('<svg class="logo logo-horizontal', $body, $path);
            self::assertStringContainsString('class="logo-mark"', $body, $path);
            self::assertStringContainsString('>UNSILENCED</text>', $body, $path);
            self::assertMatchesRegularExpression('#<link rel="icon" href="/favicon\.svg\?v=\d+" type="image/svg\+xml">#', $body, $path);
            self::assertMatchesRegularExpression('#<link rel="icon" href="/favicon-32x32\.png\?v=\d+" type="image/png" sizes="32x32">#', $body, $path);
            self::assertMatchesRegularExpression('#<link rel="apple-touch-icon" href="/apple-touch-icon\.png\?v=\d+" sizes="180x180">#', $body, $path);
            self::assertStringContainsString('<link rel="preload" href="/fonts/anton/anton-latin.woff2" as="font" type="font/woff2" crossorigin>', $body, $path);
            self::assertDoesNotMatchRegularExpression('#<img\b[^>]*\blogo#i', $body, "{$path} uses an image file for the logo");
        }

        // Public pages show the mark alone on small screens; the home link is named once.
        $home = $this->get('/')->body;
        self::assertStringContainsString('<svg class="logo logo-icon logo-compact"', $home);
        self::assertStringContainsString('<a class="site-logo" href="/" aria-label="Unsilenced home">', $home);
        self::assertStringContainsString('class="enough">Enough.</h1>', $home);
    }

    public function testFaviconFilesAreTheRightSize(): void
    {
        self::assertStringContainsString('viewBox="0 0 64 64"', (string) file_get_contents(self::publicPath('/favicon.svg')));
        self::assertSame([32, 32], array_slice((array) getimagesize(self::publicPath('/favicon-32x32.png')), 0, 2));
        self::assertSame([180, 180], array_slice((array) getimagesize(self::publicPath('/apple-touch-icon.png')), 0, 2));
        self::assertFileExists(self::publicPath('/favicon.ico'));
    }

    public function testWordmarkFontIsSelfHostedWithItsLicence(): void
    {
        $font = self::publicPath('/fonts/anton/anton-latin.woff2');
        self::assertSame('wOF2', (string) file_get_contents($font, false, null, 0, 4));
        self::assertStringContainsString('SIL Open Font License, Version 1.1', (string) file_get_contents(self::publicPath('/fonts/anton/OFL.txt')));

        $css = (string) file_get_contents(self::publicPath('/css/keel.css'));
        self::assertMatchesRegularExpression('#@font-face\s*\{[^}]*font-family:\s*"Anton";[^}]*src:\s*url\("/fonts/anton/anton-latin\.woff2"\)[^}]*font-display:\s*swap;#s', $css);
        self::assertDoesNotMatchRegularExpression('#url\(\s*["\']?(https?:)?//#i', $css);
        self::assertDoesNotMatchRegularExpression('#@import#i', $css);
    }

    public function testBrandColoursAreTokensAndViewsNameNoColours(): void
    {
        $css = (string) file_get_contents(self::publicPath('/css/keel.css'));
        self::assertStringContainsString('--brand-navy: #0B4F7C;', $css);
        self::assertStringContainsString('--brand-teal: #14B8B0;', $css);
        self::assertStringContainsString('--brand-teal-ink: #047873;', $css);
        self::assertStringContainsString('--brand-teal-ink-dark: #3ACCC4;', $css);

        $views = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::$basePath . '/views', \FilesystemIterator::SKIP_DOTS));
        foreach ($views as $file) {
            $source = (string) file_get_contents((string) $file);
            self::assertDoesNotMatchRegularExpression('/#[0-9a-f]{6}\b|#[0-9a-f]{3}\b(?![-\w])|\b(?:rgb|rgba|hsl|oklch)\(/i', $source, "{$file} names a colour");
        }
    }
}
