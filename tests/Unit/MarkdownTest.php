<?php

declare(strict_types=1);

namespace Tests\Unit;

use Keel\App\Support\Markdown;
use PHPUnit\Framework\TestCase;

class MarkdownTest extends TestCase
{
    public function testRendersTheSupportedSubset(): void
    {
        $html = Markdown::toHtml("## Heading\n\nA **bold** [link](https://example.org) and [page](/states).\n\n- one\n- two\n\n1. first\n2. second");

        self::assertStringContainsString('<h2>Heading</h2>', $html);
        self::assertStringContainsString('<strong>bold</strong>', $html);
        self::assertStringContainsString('<a href="https://example.org" rel="noopener noreferrer">link</a>', $html);
        self::assertStringContainsString('<a href="/states">page</a>', $html);
        self::assertStringContainsString('<ul><li>one</li><li>two</li></ul>', $html);
        self::assertStringContainsString('<ol><li>first</li><li>second</li></ol>', $html);
    }

    public function testEscapesHtmlAndRejectsUnsafeLinks(): void
    {
        $html = Markdown::toHtml("<script>alert(1)</script>\n\n[x](javascript:alert(1)) [y](//evil.example) [z](data:text/html,hi) <img src=x onerror=alert(1)>");

        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringNotContainsString('href="javascript', $html);
        self::assertStringNotContainsString('href="//evil', $html);
        self::assertStringNotContainsString('href="data:', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testAllowsTelAndMailtoLinks(): void
    {
        $html = Markdown::toHtml('[Call](tel:+18006564673) or [write](mailto:help@example.org)');

        self::assertStringContainsString('<a href="tel:+18006564673">Call</a>', $html);
        self::assertStringContainsString('<a href="mailto:help@example.org">write</a>', $html);
    }

    public function testToTextStripsMarkup(): void
    {
        self::assertSame('Heading Some bold and a link.', Markdown::toText("## Heading\n\nSome **bold** and a [link](https://example.org)."));
    }
}
