<?php

namespace Keel\App\Console\Commands;

use Keel\Core\Activity;
use Keel\Core\Database;

/**
 * schools:purge-fixtures [--dry-run]
 *
 * Deletes the fictional schools from tests/fixtures (UNITID 990000-990999) and
 * every row that belongs to them: their Clery figures and accountability
 * records. For a database that had the fixtures imported to try things out.
 *
 * --dry-run lists the schools and counts the rows, and deletes nothing. The
 * listing is the check that no real institution falls in the range.
 *
 * The admin activity log is history and is left as it is; the purge itself is
 * added to it. Import runs are not rows of a school and are not touched.
 */
class PurgeFixtureSchoolsCommand extends Command
{
    public const UNITID_MIN = 990000;
    public const UNITID_MAX = 990999;

    public static function usage(): string
    {
        return 'schools:purge-fixtures [--dry-run]';
    }

    public function handle(array $arguments): int
    {
        [$positional, $options] = $this->parse($arguments);

        $unknown = array_diff(array_keys($options), ['dry-run']);
        if ($positional !== [] || $unknown !== []) {
            return $this->fail('Usage: php database/console.php ' . self::usage());
        }

        $dryRun = isset($options['dry-run']);
        $connection = Database::connection();

        $schools = $connection->prepare('SELECT id, unitid, name, state FROM schools WHERE unitid BETWEEN ? AND ? ORDER BY unitid');
        $schools->execute([self::UNITID_MIN, self::UNITID_MAX]);
        $schools = $schools->fetchAll();

        $counts = $this->relatedCounts();

        $this->line(sprintf('Fixture schools (UNITID %d-%d): %d', self::UNITID_MIN, self::UNITID_MAX, count($schools)));
        foreach ($schools as $school) {
            $this->line(sprintf('  %d  %s (%s)', $school['unitid'], $school['name'], $school['state']));
        }
        $this->line(sprintf('Related Clery rows: %d', $counts['clery_stats']));
        $this->line(sprintf('Related accountability records: %d', $counts['accountability_items']));

        if ($dryRun) {
            $this->line('Dry run: nothing was deleted.');

            return 0;
        }

        if ($schools === []) {
            $this->line('Nothing to delete.');

            return 0;
        }

        $connection->beginTransaction();

        try {
            $deleted = [];
            foreach (['clery_stats', 'accountability_items'] as $table) {
                $statement = $connection->prepare(
                    "DELETE t FROM {$table} t JOIN schools s ON s.id = t.school_id WHERE s.unitid BETWEEN ? AND ?"
                );
                $statement->execute([self::UNITID_MIN, self::UNITID_MAX]);
                $deleted[$table] = $statement->rowCount();
            }

            $statement = $connection->prepare('DELETE FROM schools WHERE unitid BETWEEN ? AND ?');
            $statement->execute([self::UNITID_MIN, self::UNITID_MAX]);
            $deleted['schools'] = $statement->rowCount();

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();

            return $this->fail('Nothing was deleted: ' . $exception->getMessage());
        }

        Activity::log('schools.fixtures_purged', null, null, $deleted);

        $this->line(sprintf(
            'Deleted %d schools, %d Clery rows and %d accountability records.',
            $deleted['schools'],
            $deleted['clery_stats'],
            $deleted['accountability_items']
        ));

        return 0;
    }

    /** @return array{clery_stats: int, accountability_items: int} */
    private function relatedCounts(): array
    {
        $counts = [];
        foreach (['clery_stats', 'accountability_items'] as $table) {
            $statement = Database::connection()->prepare(
                "SELECT COUNT(*) FROM {$table} t JOIN schools s ON s.id = t.school_id WHERE s.unitid BETWEEN ? AND ?"
            );
            $statement->execute([self::UNITID_MIN, self::UNITID_MAX]);
            $counts[$table] = (int) $statement->fetchColumn();
        }

        return $counts;
    }
}
