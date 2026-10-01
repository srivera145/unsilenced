<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * /corrections: how to report an error. A static page with an email address
 * from CORRECTIONS_EMAIL; no form and nothing collected.
 */
class CorrectionsFeatureTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_ENV['CORRECTIONS_EMAIL'], $_SERVER['CORRECTIONS_EMAIL']);
        parent::tearDown();
    }

    private function setEmail(string $email): void
    {
        $_ENV['CORRECTIONS_EMAIL'] = $email;
        $_SERVER['CORRECTIONS_EMAIL'] = $email;
    }

    public function testThePageShowsTheConfiguredAddressAndCollectsNothing(): void
    {
        $this->setEmail('corrections@example.org');
        $response = $this->get('/corrections');

        self::assertSame(200, $response->status);
        self::assertStringContainsString('<title>Corrections · Unsilenced</title>', $response->body);
        self::assertStringContainsString('<a href="mailto:corrections@example.org">corrections@example.org</a>', $response->body);
        self::assertStringContainsString('id="quick-exit"', $response->body);
        self::assertStringContainsString('1-800-656-4673', $response->body);
        foreach (['Schools', 'journalists', 'members of the public', 'Clery', 'Accountability records'] as $text) {
            self::assertStringContainsString($text, $response->body);
        }

        self::assertStringNotContainsString('<form', substr($response->body, (int) strpos($response->body, '<main')), 'no form in the page body');
        self::assertDoesNotMatchRegularExpression('#<(input|textarea|select)\b#i', $response->body);
        self::assertStringNotContainsString('Set-Cookie', implode("\n", $response->headers));
    }

    public function testAMissingOrInvalidAddressIsNotShownAsALink(): void
    {
        foreach (['', 'not-an-email'] as $value) {
            $this->setEmail($value);
            $body = $this->get('/corrections')->body;

            self::assertStringNotContainsString('mailto:', $body, var_export($value, true));
            self::assertStringContainsString('The corrections email address has not been set up yet.', $body);
        }
    }

    public function testLinkedFromTheFooterTheMethodologyPageTheSitemapAndLlmsTxt(): void
    {
        foreach (['/', '/schools', '/methodology', '/resources/get-help'] as $path) {
            self::assertStringContainsString('<a href="/corrections">Corrections</a>', $this->get($path)->body, "{$path} footer");
        }

        $methodology = $this->get('/methodology')->body;
        $prose = substr($methodology, (int) strpos($methodology, '<article'), (int) strpos($methodology, '</article>') - (int) strpos($methodology, '<article'));
        self::assertStringContainsString('href="/corrections"', $prose, 'linked from the methodology text itself');

        self::assertStringContainsString('/corrections</loc>', $this->get('/sitemap.xml')->body);
        self::assertStringContainsString('/corrections)', $this->get('/llms.txt')->body);
    }
}
