<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Link previews: Open Graph and Twitter card tags on every public page, with
 * this site's own 1200x630 image at an absolute URL built from APP_URL.
 */
class SharingFeatureTest extends TestCase
{
    private const SHARED_PAGES = [
        '/',
        '/schools',
        '/schools/ny',
        '/schools/ny/fixture-state-university',
        '/schools/tx/fixture-college-of-the-arts',
        '/resources',
        '/resources/get-help',
        '/states',
        '/states/ny',
        '/methodology',
        '/corrections',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importFixtures();
    }

    /** @return array<string, string> property or name => content */
    private function shareTags(string $html): array
    {
        preg_match_all('#<meta (?:property|name)="((?:og|twitter):[a-z:_]+)" content="([^"]*)">#', $html, $matches, PREG_SET_ORDER);
        $tags = [];
        foreach ($matches as [, $key, $value]) {
            $tags[$key] = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
        }

        return $tags;
    }

    public function testEveryPublicPageHasOpenGraphAndTwitterCardTags(): void
    {
        $appUrl = rtrim((string) $_ENV['APP_URL'], '/');

        foreach (self::SHARED_PAGES as $path) {
            $tags = $this->shareTags($this->get($path)->body);

            foreach (['og:type', 'og:site_name', 'og:title', 'og:description', 'og:url', 'og:image', 'og:image:alt', 'twitter:title', 'twitter:description', 'twitter:image', 'twitter:image:alt'] as $key) {
                self::assertNotSame('', $tags[$key] ?? '', "{$path} {$key}");
            }

            self::assertSame($appUrl . '/share-card.png', $tags['og:image'], $path);
            self::assertSame($tags['og:image'], $tags['twitter:image'], $path);
            self::assertSame(['1200', '630', 'image/png'], [$tags['og:image:width'], $tags['og:image:height'], $tags['og:image:type']], $path);
            self::assertSame('summary_large_image', $tags['twitter:card'], $path);
            self::assertSame($appUrl . strtok($path, '?'), $tags['og:url'], $path);

            // Nothing in a preview points anywhere but this site.
            foreach ($tags as $key => $value) {
                if (preg_match('#^https?://#', $value)) {
                    self::assertStringStartsWith($appUrl . '/', $value, "{$path} {$key}");
                }
            }
        }
    }

    public function testSchoolPagesUseTheSchoolNameAsTheTitle(): void
    {
        $tags = $this->shareTags($this->get('/schools/ny/fixture-state-university')->body);

        self::assertSame('Fixture State University · Unsilenced', $tags['og:title']);
        self::assertSame($tags['og:title'], $tags['twitter:title']);
    }

    public function testTheShareImageIsASelfHosted1200By630Png(): void
    {
        $file = self::$basePath . '/public_html/share-card.png';
        self::assertFileExists($file);

        $size = getimagesize($file);
        self::assertSame([1200, 630, IMAGETYPE_PNG], [$size[0], $size[1], $size[2]]);
        self::assertLessThan(300 * 1024, filesize($file), 'small enough for any preview fetcher');
    }

    public function testErrorAdminAndSignInPagesHaveNoPreviewTags(): void
    {
        self::assertSame([], $this->shareTags($this->get('/no-such-page')->body));
        self::assertSame([], $this->shareTags($this->get('/login')->body));

        $this->actingAsAdmin();
        self::assertSame([], $this->shareTags($this->get('/admin')->body));
    }
}
