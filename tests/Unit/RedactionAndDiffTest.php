<?php

declare(strict_types=1);

namespace Tests\Unit;

use Keel\App\Services\Survivor\RedactionCheck;
use Keel\App\Services\Survivor\WordDiff;
use PHPUnit\Framework\TestCase;

/**
 * Admins may remove words and add placeholders. They may not add a word, move
 * one, or bracket something of their own.
 */
class RedactionAndDiffTest extends TestCase
{
    private const ORIGINAL = "My RA, Tyler, told me not to report it. The Title IX office never called back.\nI left in May.";

    public function testRemovingWordsAndAddingPlaceholdersIsAllowed(): void
    {
        $published = "My RA, [name removed], told me not to report it. The Title IX office never called back.\nI left in [date removed].";

        self::assertTrue(RedactionCheck::check(self::ORIGINAL, $published)['ok']);
        self::assertTrue(RedactionCheck::check(self::ORIGINAL, 'The Title IX office never called back.')['ok']);
        self::assertTrue(RedactionCheck::check(self::ORIGINAL, 'my ra told me NOT to report it')['ok'], 'case and punctuation may change');
        self::assertTrue(RedactionCheck::check(self::ORIGINAL, '')['ok']);
    }

    public function testAddingAWordIsRefusedAndNamed(): void
    {
        $result = RedactionCheck::check(self::ORIGINAL, 'My RA told me not to report it. The school covered it up. The Title IX office never called back.');

        self::assertFalse($result['ok']);
        foreach (['school', 'covered', 'up'] as $word) {
            self::assertContains($word, $result['added']);
        }
        // A second "the" and "it" are extra too; the words after the addition are not.
        foreach (['title', 'office', 'never', 'called', 'back'] as $word) {
            self::assertNotContains($word, $result['added'], 'one added word does not flag everything after it');
        }
    }

    public function testMovingWordsIsRefused(): void
    {
        self::assertFalse(RedactionCheck::check(self::ORIGINAL, 'The Title IX office never called back. My RA told me not to report it.')['ok']);
    }

    public function testOnlyConfiguredPlaceholdersMayBeBracketed(): void
    {
        $result = RedactionCheck::check(self::ORIGINAL, 'My RA, [he was later expelled], told me not to report it.');

        self::assertFalse($result['ok']);
        self::assertSame(['[he was later expelled]'], $result['unknown_brackets']);
    }

    public function testTheDiffMarksRemovalsAndPlaceholdersAndEscapesEverything(): void
    {
        $original = 'Tyler <b>said</b> & left.';
        $published = '[name removed] <b>said</b> & left.';
        $diff = WordDiff::sideBySide($original, $published);

        self::assertStringContainsString('<del class="diff-del">Tyler</del>', $diff['left']);
        self::assertStringContainsString('<ins class="diff-ins">[name removed]</ins>', $diff['right']);
        self::assertStringContainsString('&lt;b&gt;', $diff['left']);
        self::assertStringNotContainsString('<b>', $diff['left'] . $diff['right']);
        self::assertSame(1, $diff['removed']);
        self::assertSame(1, $diff['inserted']);
    }

    public function testTheDiffHandlesAFullLengthAccount(): void
    {
        $original = trim(str_repeat('The office told me to wait and I waited for weeks. ', 95));
        $published = str_replace('weeks', '[detail removed]', $original);
        $diff = WordDiff::sideBySide($original, $published);

        self::assertSame(95, $diff['removed']);
        self::assertSame(95, $diff['inserted']);
    }
}
