<?php

declare(strict_types=1);

namespace Tests\Feature;

use Keel\App\Console\Commands\ImportCleryCommand;
use Keel\App\Console\Commands\ImportSchoolsCommand;
use Keel\App\Jobs\ImportSchoolsJob;
use Keel\App\Models\ImportRun;
use Keel\Core\Database;
use Tests\TestCase;

class ImportFeatureTest extends TestCase
{
    private array $out = [];
    private array $err = [];

    private function fixture(string $path): string
    {
        return self::$basePath . '/tests/fixtures/' . $path;
    }

    private function schoolsCommand(): ImportSchoolsCommand
    {
        return new ImportSchoolsCommand(fn (string $l) => $this->out[] = $l, fn (string $l) => $this->err[] = $l);
    }

    private function cleryCommand(): ImportCleryCommand
    {
        return new ImportCleryCommand(fn (string $l) => $this->out[] = $l, fn (string $l) => $this->err[] = $l);
    }

    private function rowCount(string $table): int
    {
        return (int) Database::connection()->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }

    private function checksum(): string
    {
        return (string) Database::connection()->query(
            "SELECT MD5(GROUP_CONCAT(CONCAT_WS(':', school_id, year, location, IFNULL(rape,'n'), IFNULL(fondling,'n'), IFNULL(incest,'n'),
                IFNULL(statutory_rape,'n'), IFNULL(dating_violence,'n'), IFNULL(domestic_violence,'n'), IFNULL(stalking,'n')) ORDER BY school_id, year, location))
             FROM clery_stats"
        )->fetchColumn();
    }

    private function runAll(): void
    {
        self::assertSame(0, $this->schoolsCommand()->handle([$this->fixture('ipeds/HD2023.csv'), '--now']), implode("\n", $this->err));
        self::assertSame(0, $this->schoolsCommand()->handle([$this->fixture('ipeds/DRVEF2023.csv'), '--now']), implode("\n", $this->err));

        foreach (['2021', '2022', '2023'] as $year) {
            foreach (glob($this->fixture('clery/*.csv')) as $file) {
                self::assertSame(0, $this->cleryCommand()->handle([$file, $year, '--now']), implode("\n", $this->err));
            }
        }
    }

    public function testRunningEveryImportTwiceLeavesIdenticalRows(): void
    {
        $this->runAll();
        $schools = $this->rowCount('schools');
        $stats = $this->rowCount('clery_stats');
        $checksum = $this->checksum();

        self::assertSame(8, $schools, 'eight valid institutions in HD2023.csv');
        self::assertSame(84, $stats, '7 schools with Clery rows x 3 years x 4 locations');

        $lastRun = (int) Database::connection()->query('SELECT MAX(id) FROM import_runs')->fetchColumn();
        $this->runAll();

        self::assertSame($schools, $this->rowCount('schools'));
        self::assertSame($stats, $this->rowCount('clery_stats'));
        self::assertSame($checksum, $this->checksum());

        $second = Database::connection()->query("SELECT SUM(rows_added) a, SUM(rows_updated) u, COUNT(*) c FROM import_runs WHERE id > {$lastRun} AND status = 'complete'")->fetch();
        self::assertSame(26, (int) $second['c']);
        self::assertSame(0, (int) $second['a'], 'second run adds nothing');
        self::assertSame(0, (int) $second['u'], 'second run changes nothing');
    }

    public function testCampusRowsAreSummedAndBlankCellsStayNull(): void
    {
        $this->importFixtures();

        $row = Database::connection()->query(
            "SELECT c.rape, c.dating_violence FROM clery_stats c JOIN schools s ON s.id = c.school_id
             WHERE s.unitid = 990005 AND c.year = 2023 AND c.location = 'on_campus'"
        )->fetch();
        self::assertSame(21, (int) $row['rape'], '18 main campus + 3 branch');
        self::assertSame(26, (int) $row['dating_violence'], 'from the VAWA file, merged into the same row');

        $blank = Database::connection()->query(
            "SELECT c.rape, c.stalking FROM clery_stats c JOIN schools s ON s.id = c.school_id
             WHERE s.unitid = 990003 AND c.year = 2023 AND c.location = 'on_campus_housing'"
        )->fetch();
        self::assertNull($blank['rape']);
        self::assertNull($blank['stalking']);
    }

    public function testWindows1252NamesAndDuplicateSlugsAreHandled(): void
    {
        $this->importFixtures();

        $school = Database::connection()->query('SELECT name, slug FROM schools WHERE unitid = 990008')->fetch();
        self::assertSame('Université Fixture de Puerto Rico', $school['name']);
        self::assertSame('universite-fixture-de-puerto-rico', $school['slug']);

        $utica = Database::connection()->query('SELECT slug FROM schools WHERE unitid = 990007')->fetchColumn();
        self::assertSame('fixture-state-university-utica', $utica);
    }

    public function testMissingRequiredColumnFailsLoudlyAndQueuesNothing(): void
    {
        $exit = $this->schoolsCommand()->handle([$this->fixture('ipeds/HD_missing_control.csv')]);
        $error = implode("\n", $this->err);

        self::assertSame(1, $exit);
        self::assertStringContainsString('missing required columns', $error);
        self::assertStringContainsString('"CONTROL"', $error);
        self::assertStringContainsString('"ENRTOT"', $error);
        self::assertStringContainsString('config/unsilenced.php', $error);
        self::assertSame(0, $this->rowCount('import_runs'));
        self::assertSame(0, $this->rowCount('jobs'));
    }

    public function testCleryYearTheFileDoesNotCoverFailsLoudly(): void
    {
        $exit = $this->cleryCommand()->handle([$this->fixture('clery/oncampuscrime.csv'), '2019']);
        $error = implode("\n", $this->err);

        self::assertSame(1, $exit);
        self::assertStringContainsString('no offense columns for 2019', $error);
        self::assertStringContainsString('RAPE19', $error);
        self::assertStringContainsString('21, 22, 23', $error);
        self::assertSame(0, $this->rowCount('import_runs'));
    }

    public function testUnknownLocationFailsLoudly(): void
    {
        $copy = sys_get_temp_dir() . '/crime-' . bin2hex(random_bytes(3)) . '.csv';
        copy($this->fixture('clery/oncampuscrime.csv'), $copy);

        try {
            self::assertSame(1, $this->cleryCommand()->handle([$copy, '2023']));
            self::assertStringContainsString('--location=', implode("\n", $this->err));
            self::assertSame(0, $this->cleryCommand()->handle([$copy, '2023', '--location=noncampus']));
        } finally {
            @unlink($copy);
        }
    }

    public function testImportIsQueuedAndTheJobCompletesTheRun(): void
    {
        self::assertSame(0, $this->schoolsCommand()->handle([$this->fixture('ipeds/HD2023.csv')]));

        $job = Database::connection()->query('SELECT * FROM jobs')->fetch();
        self::assertSame(ImportSchoolsJob::class, $job['job_class']);
        self::assertSame(0, $this->rowCount('schools'), 'nothing imported until the worker runs');

        $payload = json_decode((string) $job['payload'], true);
        (new ImportSchoolsJob())->handle($payload);

        $run = ImportRun::find((int) $payload['import_run_id']);
        self::assertSame('complete', $run['status']);
        self::assertSame(10, (int) $run['rows_read']);
        self::assertSame(8, (int) $run['rows_added']);
        self::assertSame(2, (int) $run['rows_skipped']);
        self::assertSame(2, (int) $run['error_count']);
        self::assertStringContainsString('unknown state code', implode(' ', ImportRun::errors($run)));
        self::assertSame(8, $this->rowCount('schools'));
    }

    public function testAFileChangedAfterQueueingIsRefused(): void
    {
        $copy = sys_get_temp_dir() . '/HD2023-' . bin2hex(random_bytes(3)) . '.csv';
        copy($this->fixture('ipeds/HD2023.csv'), $copy);

        try {
            self::assertSame(0, $this->schoolsCommand()->handle([$copy]));
            file_put_contents($copy, "990050,Edited Afterwards,Somewhere,NY,1,1,,,,,1,1\r\n", FILE_APPEND);

            $payload = json_decode((string) Database::connection()->query('SELECT payload FROM jobs')->fetchColumn(), true);
            (new ImportSchoolsJob())->handle($payload);

            $run = ImportRun::find((int) $payload['import_run_id']);
            self::assertSame('failed', $run['status']);
            self::assertStringContainsString('changed after this import was queued', (string) $run['message']);
            self::assertSame(0, $this->rowCount('schools'));
        } finally {
            @unlink($copy);
        }
    }
}
