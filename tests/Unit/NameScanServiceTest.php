<?php

declare(strict_types=1);

namespace Tests\Unit;

use Keel\App\Services\Survivor\NameScanService;
use PHPUnit\Framework\TestCase;

/**
 * The check a survivor sees before submitting: names, contact details, room
 * numbers, chapter names and handles are found and highlighted.
 */
class NameScanServiceTest extends TestCase
{
    private function found(string $text, array $ignore = []): array
    {
        $result = [];
        foreach ((new NameScanService())->scan($text, $ignore) as $finding) {
            $result[] = [$finding['type'], $finding['text']];
        }

        return $result;
    }

    private function assertFinds(string $type, string $expected, string $text, array $ignore = []): void
    {
        self::assertContains([$type, $expected], $this->found($text, $ignore), "in: {$text}");
    }

    public function testNames(): void
    {
        $this->assertFinds('name', 'Tyler', 'After the party Tyler walked me back.');
        $this->assertFinds('name', 'Jane Doe', 'I told Jane Doe what happened.');
        $this->assertFinds('name', 'Coach Miller', 'Then Coach Miller said to drop it.');
        $this->assertFinds('name', 'Brock', 'a guy named Brock from my floor');
        $this->assertFinds('name', 'Will', 'I told Will the next day.');
        $this->assertFinds('name', 'Jamal', 'Jamal');
    }

    public function testOrdinaryWordsAtTheStartOfASentenceAreLeftAlone(): void
    {
        self::assertSame([], $this->found('Will anyone believe me? May was the worst month. Grace is not something they showed.'));
        self::assertSame([], $this->found('I went to the Title IX office in March.'));
    }

    public function testTheSchoolsOwnNameIsNotAName(): void
    {
        self::assertSame([], $this->found('I reported it to Fixture State University.', ['Fixture', 'State', 'University']));
    }

    public function testContactDetails(): void
    {
        $this->assertFinds('phone', '(555) 123-4567', 'He kept calling from (555) 123-4567 every night.');
        $this->assertFinds('phone', '555.123.4567', 'call 555.123.4567');
        $this->assertFinds('phone', '+1 555 123 4567', 'number +1 555 123 4567.');
        $this->assertFinds('phone', '5551234567', 'texts from 5551234567');
        $this->assertFinds('email', 'someone.else@example.edu', 'He emailed from someone.else@example.edu twice.');
        $this->assertFinds('address', '412 Oak Street', 'It was at 412 Oak Street, after midnight.');
        $this->assertFinds('address', 'Linden Avenue', 'the house on Linden Avenue near campus');
    }

    public function testRoomsChaptersAndHandles(): void
    {
        $this->assertFinds('room', 'room 214', 'He came to room 214.');
        $this->assertFinds('room', 'Apt 3B', 'his place, Apt 3B');
        $this->assertFinds('room', 'Baker Hall 302', 'in Baker Hall 302 that night');
        $this->assertFinds('greek', 'Sigma Chi', 'at the Sigma Chi house');
        $this->assertFinds('greek', 'Kappa Kappa Gamma', 'a Kappa Kappa Gamma event');
        $this->assertFinds('greek', 'SAE', 'an SAE party');
        $this->assertFinds('greek', 'ΣΧ', 'the ΣΧ formal');
        $this->assertFinds('handle', '@jdoe_22', 'his account @jdoe_22 posted it');
        $this->assertFinds('handle', 'snap: jdoe22', 'his snap: jdoe22');
        $this->assertFinds('handle', 'instagram.com/jdoe22', 'see instagram.com/jdoe22');
    }

    /** Phase 2.1: roles and titles that point to one person, with the words that make them so. */
    public function testRolesAndTitles(): void
    {
        $this->assertFinds('role', 'my RA', 'After the party my RA walked me back.');
        $this->assertFinds('role', 'the coach', 'Later the coach said to drop it.');
        $this->assertFinds('role', 'My chemistry professor', 'My chemistry professor knew.');
        $this->assertFinds('role', 'a professor of biology', 'He was a professor of biology there.');
        $this->assertFinds('role', 'the head coach', 'I told the head coach.');
        $this->assertFinds('role', 'team captain', 'He was team captain that year.');
        $this->assertFinds('role', 'the chapter president', 'so did the chapter president');
        $this->assertFinds('role', 'a former RA', 'a former RA was there');
        $this->assertFinds('role', 'TAs', 'two TAs saw it');
        $this->assertFinds('role', 'our resident advisor', 'our resident advisor');
        $this->assertFinds('role', 'my academic advisor', 'Even my academic advisor helped.');
        $this->assertFinds('role', 'the soccer team', 'He was on the soccer team.');
        $this->assertFinds('role', "the women's lacrosse team", "someone from the women's lacrosse team");
    }

    public function testRolesDoNotHideANameOrMatchInsideWords(): void
    {
        self::assertSame([['name', 'Coach Miller']], $this->found('Then Coach Miller said nothing.'), 'the name, not "Then Coach"');
        self::assertSame([['role', 'My RA'], ['name', 'Tyler']], $this->found('My RA, Tyler, walked me back.'));
        self::assertSame([], $this->found('We met in the theatre near the tarmac after a Ramadan dinner; the data was in an area report.'));
        self::assertSame([], $this->found('The Title IX office never called back.'), 'the office handling it is not a role to remove');
    }

    public function testAnEmailIsNotAlsoAHandle(): void
    {
        self::assertSame([['email', 'a.person@example.org']], $this->found('write to a.person@example.org'));
    }

    public function testSegmentsJoinBackIntoTheExactText(): void
    {
        $text = "I told Jane Doe in room 214 — she said “call 555-123-4567”.\nNothing else.";
        $scanner = new NameScanService();
        $findings = $scanner->scan($text);
        $segments = $scanner->segments($text, $findings);

        self::assertSame($text, implode('', array_column($segments, 'text')));
        self::assertSame(['name', 'room', 'phone'], array_values(array_filter(array_column($segments, 'type'))));
        foreach ($segments as $segment) {
            if ($segment['type'] !== null) {
                self::assertNotNull($segment['label']);
            }
        }
    }

    public function testFindingsNeverOverlap(): void
    {
        $findings = (new NameScanService())->scan('Coach Tyler Brown of Sigma Chi at 12 Elm Street called 555-123-4567.');
        $previousEnd = -1;
        foreach ($findings as $finding) {
            self::assertGreaterThanOrEqual($previousEnd, $finding['start']);
            $previousEnd = $finding['end'];
        }
    }
}
