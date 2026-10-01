<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class PublicDiscoveryEndpointsFeatureTest extends TestCase
{
    private function baseUrl(): string
    {
        return rtrim((string) ($_ENV['APP_URL'] ?? 'http://localhost'), '/');
    }

    public function testSitemapListsPublicPagesSchoolsStatesAndResourcesButNotAdmin(): void
    {
        $this->importFixtures();
        $response = $this->get('/sitemap.xml');
        $base = $this->baseUrl();

        self::assertSame(200, $response->status);
        self::assertStringContainsString('application/xml', (string) $response->header('Content-Type'));

        foreach (['/', '/schools', '/methodology', '/resources', '/states', '/resources/get-help', '/states/ny', '/schools/ny', '/schools/ny/fixture-state-university', '/schools/pr/universite-fixture-de-puerto-rico'] as $path) {
            self::assertStringContainsString('<loc>' . $base . $path . '</loc>', $response->body, $path);
        }

        self::assertStringNotContainsString('{', $response->body);
        self::assertStringNotContainsString('/admin', $response->body);
        self::assertStringNotContainsString('/login', $response->body);
    }

    public function testRobotsTxtDisallowsAdminAndSignIn(): void
    {
        $response = $this->get('/robots.txt');

        self::assertSame(200, $response->status);
        self::assertStringContainsString("Disallow: /admin\n", $response->body);
        self::assertStringContainsString("Disallow: /admin/\n", $response->body);
        self::assertStringContainsString("Disallow: /login\n", $response->body);
        self::assertStringContainsString("Disallow: /auth/\n", $response->body);
        self::assertStringNotContainsString('Disallow: /schools', $response->body);
        self::assertStringContainsString('Sitemap: ' . $this->baseUrl() . '/sitemap.xml', $response->body);
    }

    public function testLlmsTxtDescribesTheSiteAndItsDataSources(): void
    {
        $this->importFixtures();
        $response = $this->get('/llms.txt');

        self::assertSame(200, $response->status);
        self::assertStringContainsString('text/plain', (string) $response->header('Content-Type'));
        self::assertStringContainsString('# Unsilenced', $response->body);
        self::assertStringContainsString('## Data sources', $response->body);
        self::assertStringContainsString('Campus Safety and Security', $response->body);
        self::assertStringContainsString('IPEDS', $response->body);
        self::assertStringContainsString('2021, 2022 and 2023', $response->body);
        self::assertStringContainsString('never names individuals', $response->body);
        self::assertStringContainsString($this->baseUrl() . '/methodology', $response->body);
        self::assertStringContainsString('1-800-656-4673', $response->body);
    }
}
