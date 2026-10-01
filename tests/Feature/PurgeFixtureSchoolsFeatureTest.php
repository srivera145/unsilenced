<?php

declare(strict_types=1);

namespace Tests\Feature;

use Keel\App\Console\Commands\PurgeFixtureSchoolsCommand;
use Keel\Core\Database;
use Tests\TestCase;

/**
 * schools:purge-fixtures removes the fictional 9900xx schools and their rows,
 * and nothing else. --dry-run only reports.
 */
class PurgeFixtureSchoolsFeatureTest extends TestCase
{
    private array $out = [];
    private array $err = [];
    private int $realSchoolId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importFixtures();

        // A real-range school with its own rows, which must survive.
        $db = Database::connection();
        $db->exec("INSERT INTO schools (unitid, name, slug, city, state, control, enrollment) VALUES (100654, 'Real Range College', 'real-range-college', 'Normal', 'AL', 'public', 6000)");
        $this->realSchoolId = (int) $db->lastInsertId();
        $db->exec("INSERT INTO clery_stats (school_id, year, location, rape) VALUES ({$this->realSchoolId}, 2023, 'on_campus', 2)");

        $fixtureId = (int) $db->query('SELECT id FROM schools WHERE unitid = 990001')->fetchColumn();
        foreach ([$fixtureId, $this->realSchoolId] as $schoolId) {
            $db->prepare("INSERT INTO accountability_items (school_id, type, item_date, summary, status, source_name, source_url) VALUES (?, 'lawsuit', '2024-01-01', 'A summary.', 'open', 'Source', 'https://example.org/')")
                ->execute([$schoolId]);
        }
    }

    private function command(): PurgeFixtureSchoolsCommand
    {
        return new PurgeFixtureSchoolsCommand(fn (string $l) => $this->out[] = $l, fn (string $l) => $this->err[] = $l);
    }

    private function counts(): array
    {
        $db = Database::connection();
        $fixture = 'IN (SELECT id FROM schools WHERE unitid BETWEEN 990000 AND 990999)';

        return [
            'fixture_schools' => (int) $db->query('SELECT COUNT(*) FROM schools WHERE unitid BETWEEN 990000 AND 990999')->fetchColumn(),
            'fixture_clery' => (int) $db->query("SELECT COUNT(*) FROM clery_stats WHERE school_id {$fixture}")->fetchColumn(),
            'fixture_items' => (int) $db->query("SELECT COUNT(*) FROM accountability_items WHERE school_id {$fixture}")->fetchColumn(),
            'real_schools' => (int) $db->query("SELECT COUNT(*) FROM schools WHERE id = {$this->realSchoolId}")->fetchColumn(),
            'real_clery' => (int) $db->query("SELECT COUNT(*) FROM clery_stats WHERE school_id = {$this->realSchoolId}")->fetchColumn(),
            'real_items' => (int) $db->query("SELECT COUNT(*) FROM accountability_items WHERE school_id = {$this->realSchoolId}")->fetchColumn(),
        ];
    }

    public function testDryRunListsAndCountsButDeletesNothing(): void
    {
        $before = $this->counts();
        self::assertGreaterThan(0, $before['fixture_schools']);
        self::assertGreaterThan(0, $before['fixture_clery']);

        self::assertSame(0, $this->command()->handle(['--dry-run']));

        self::assertSame($before, $this->counts());
        self::assertContains("Fixture schools (UNITID 990000-990999): {$before['fixture_schools']}", $this->out);
        self::assertContains('  990001  Fixture State University (NY)', $this->out);
        self::assertContains("Related Clery rows: {$before['fixture_clery']}", $this->out);
        self::assertContains('Related accountability records: 1', $this->out);
        self::assertContains('Dry run: nothing was deleted.', $this->out);
        self::assertStringNotContainsString('Real Range College', implode("\n", $this->out));
    }

    public function testRunDeletesFixturesAndTheirRowsOnly(): void
    {
        $before = $this->counts();

        self::assertSame(0, $this->command()->handle([]));

        $after = $this->counts();
        self::assertSame(0, $after['fixture_schools']);
        self::assertSame(0, $after['fixture_clery']);
        self::assertSame(0, $after['fixture_items']);
        self::assertSame([1, 1, 1], [$after['real_schools'], $after['real_clery'], $after['real_items']]);
        self::assertContains(
            "Deleted {$before['fixture_schools']} schools, {$before['fixture_clery']} Clery rows and 1 accountability records.",
            $this->out
        );

        $logged = Database::connection()->query("SELECT metadata FROM activity_log WHERE action = 'schools.fixtures_purged'")->fetchColumn();
        self::assertSame(['clery_stats' => $before['fixture_clery'], 'accountability_items' => 1, 'schools' => $before['fixture_schools']], json_decode((string) $logged, true));

        // Running it again finds nothing.
        $this->out = [];
        self::assertSame(0, $this->command()->handle([]));
        self::assertContains('Nothing to delete.', $this->out);
    }

    public function testUnknownArgumentsAreRefused(): void
    {
        self::assertSame(1, $this->command()->handle(['--dryrun']));
        self::assertSame(['Usage: php database/console.php schools:purge-fixtures [--dry-run]'], $this->err);
        self::assertGreaterThan(0, $this->counts()['fixture_schools']);
    }
}
