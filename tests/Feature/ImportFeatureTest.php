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
        self::assertSame(0, $this->schoolsCommand()->handle([$this->fixture('ipeds/hd2023.csv'), '--now']), implode("\n", $this->err));
        self::assertSame(0, $this->schoolsCommand()->handle([$this->fixture('ipeds/drvef2023.csv'), '--now']), implode("\n", $this->err));

        foreach (glob($this->fixture('clery/*.csv')) as $file) {
            self::assertSame(0, $this->cleryCommand()->handle([$file, '--now']), implode("\n", $this->err));
        }
    }

    private function cleryValue(int $unitid, int $year, string $location, string $offense): ?int
    {
        $statement = Database::connection()->prepare(
            "SELECT c.{$offense} FROM clery_stats c JOIN schools s ON s.id = c.school_id
             WHERE s.unitid = ? AND c.year = ? AND c.location = ?"
        );
        $statement->execute([$unitid, $year, $location]);
        $value = $statement->fetchColumn();

        return $value === false || $value === null ? null : (int) $value;
    }

    public function testRunningEveryImportTwiceLeavesIdenticalRows(): void
    {
        $this->runAll();
        $schools = $this->rowCount('schools');
        $stats = $this->rowCount('clery_stats');
        $checksum = $this->checksum();

        self::assertSame(8, $schools, 'eight valid institutions in hd2023.csv');
        self::assertSame(84, $stats, '7 schools with Clery rows x 3 years x 4 locations');

        $lastRun = (int) Database::connection()->query('SELECT MAX(id) FROM import_runs')->fetchColumn();
        $this->runAll();

        self::assertSame($schools, $this->rowCount('schools'));
        self::assertSame($stats, $this->rowCount('clery_stats'));
        self::assertSame($checksum, $this->checksum());

        $second = Database::connection()->query("SELECT SUM(rows_added) a, SUM(rows_updated) u, COUNT(*) c FROM import_runs WHERE id > {$lastRun} AND status = 'complete'")->fetch();
        self::assertSame(10, (int) $second['c'], '2 IPEDS files + 8 Clery files');
        self::assertSame(0, (int) $second['a'], 'second run adds nothing');
        self::assertSame(0, (int) $second['u'], 'second run changes nothing');
    }

    public function testAThreeYearFileImportsEveryYearInOneRun(): void
    {
        $this->schoolsCommand()->handle([$this->fixture('ipeds/hd2023.csv'), '--now']);
        self::assertSame(0, $this->cleryCommand()->handle([$this->fixture('clery/Oncampuscrime212223.csv'), '--now']), implode("\n", $this->err));

        self::assertStringContainsString('2021–2023 (newest year in file 2023), location on_campus', implode("\n", $this->out));
        self::assertStringContainsString('covering 2021, 2022 and 2023', implode("\n", $this->out));

        $years = Database::connection()->query("SELECT DISTINCT year FROM clery_stats WHERE location = 'on_campus' ORDER BY year")->fetchAll(\PDO::FETCH_COLUMN);
        self::assertSame([2021, 2022, 2023], array_map('intval', $years));
        self::assertSame(8, $this->cleryValue(990001, 2021, 'on_campus', 'rape'));
        self::assertSame(11, $this->cleryValue(990001, 2022, 'on_campus', 'rape'));
        self::assertSame(9, $this->cleryValue(990001, 2023, 'on_campus', 'rape'));
        self::assertNull($this->cleryValue(990001, 2023, 'on_campus', 'stalking'), 'the VAWA file has not been imported');

        $run = ImportRun::find((int) Database::connection()->query('SELECT MAX(id) FROM import_runs')->fetchColumn());
        self::assertNull($run['data_year'], 'no single year: every year in the file');
        self::assertSame('2021–2023', ImportRun::yearsLabel($run));
    }

    public function testAdminImportPagesShowTheYearsARunCovered(): void
    {
        $this->schoolsCommand()->handle([$this->fixture('ipeds/hd2023.csv'), '--now']);
        $this->cleryCommand()->handle([$this->fixture('clery/Oncampuscrime212223.csv'), '--now']);
        $runId = (int) Database::connection()->query('SELECT MAX(id) FROM import_runs')->fetchColumn();

        $this->actingAsAdmin();
        $index = $this->get('/admin/imports');
        self::assertSame(200, $index->status);
        self::assertStringContainsString('<td class="nums">2021–2023</td>', $index->body);

        $show = $this->get('/admin/imports/' . $runId);
        self::assertSame(200, $show->status);
        self::assertStringContainsString('Calendar years', $show->body);
        self::assertStringContainsString('newest year in the file: 2023', $show->body);
        self::assertStringContainsString('Rape ← RAPE21, RAPE22, RAPE23', $show->body);
    }

    public function testAYearCanStillBeImportedOnItsOwn(): void
    {
        $this->schoolsCommand()->handle([$this->fixture('ipeds/hd2023.csv'), '--now']);
        self::assertSame(0, $this->cleryCommand()->handle([$this->fixture('clery/Oncampuscrime212223.csv'), '2022', '--now']), implode("\n", $this->err));

        $years = Database::connection()->query('SELECT DISTINCT year FROM clery_stats')->fetchAll(\PDO::FETCH_COLUMN);
        self::assertSame([2022], array_map('intval', $years));
    }

    public function testTheNewestFileWinsWhateverTheImportOrder(): void
    {
        $older = glob($this->fixture('clery-2020-2022/*.csv'));
        self::assertCount(2, $older);
        $clery = new \Keel\App\Services\Imports\CleryImporter();

        // Newest file first, then the older one.
        $this->importFixtures();
        foreach ($older as $file) {
            $clery->import($file, null, null);
        }
        $newestFirst = $this->checksum();

        // A year only the older file covers: main campus 5 + nothing for the
        // Downtown Center in 2020.
        self::assertSame(5, $this->cleryValue(990001, 2020, 'on_campus', 'rape'));
        // Main campus revised in the newer file from 7 to 8; the Downtown
        // Center closed and is only in the older file, and its 2 stay: 8 + 2.
        self::assertSame(10, $this->cleryValue(990001, 2021, 'on_campus', 'rape'));
        self::assertSame(12, $this->cleryValue(990001, 2022, 'on_campus', 'rape'), '11 + 1');
        // Revised in the newer file: 9 -> 2 fondling.
        self::assertSame(2, $this->cleryValue(990002, 2021, 'on_campus', 'fondling'));
        // The newer file adds a branch campus: 15 + 2.
        self::assertSame(17, $this->cleryValue(990005, 2021, 'on_campus', 'rape'));
        // The newer file leaves this blank; a blank does not erase the older figure.
        self::assertSame(1, $this->cleryValue(990003, 2021, 'on_campus_housing', 'rape'));
        self::assertNull($this->cleryValue(990003, 2022, 'on_campus_housing', 'rape'));

        $campuses = Database::connection()->query(
            "SELECT campus_id, rape, rape_vintage FROM clery_campus_stats
             WHERE campus_id IN ('990001001', '990001002') AND year = 2021 AND location = 'on_campus' ORDER BY campus_id"
        )->fetchAll();
        self::assertSame(['990001001', 8, 2023], [$campuses[0]['campus_id'], (int) $campuses[0]['rape'], (int) $campuses[0]['rape_vintage']]);
        self::assertSame(['990001002', 2, 2022], [$campuses[1]['campus_id'], (int) $campuses[1]['rape'], (int) $campuses[1]['rape_vintage']]);

        // Re-importing the older file changes nothing.
        foreach ($older as $file) {
            $result = $clery->import($file, null, null);
            self::assertSame(0, $result['rows_added'], basename($file));
            self::assertSame(0, $result['rows_updated'], basename($file));
        }

        // Older file first, then the newest: the same rows.
        Database::connection()->exec('DELETE FROM clery_campus_stats');
        Database::connection()->exec('DELETE FROM clery_stats');
        foreach ($older as $file) {
            $clery->import($file, null, null);
        }
        foreach (glob($this->fixture('clery/*.csv')) as $file) {
            $clery->import($file, null, null);
        }

        self::assertSame($newestFirst, $this->checksum());
    }

    public function testOnlySchoolsWithCleryFiguresAreMarkedForPublicLists(): void
    {
        $this->importFixtures();

        $flags = Database::connection()->query('SELECT unitid, has_clery_data FROM schools ORDER BY unitid')->fetchAll(\PDO::FETCH_KEY_PAIR);
        self::assertSame('0', (string) $flags[990004], 'Fixture College of the Arts is in IPEDS but not in the Clery files');
        unset($flags[990004]);
        self::assertSame(['1'], array_values(array_unique(array_map('strval', $flags))));
    }

    public function testHateCrimeAndOtherCleryFilesAreRefused(): void
    {
        $dir = sys_get_temp_dir() . '/clery-' . bin2hex(random_bytes(3));
        mkdir($dir);

        try {
            // Hate-crime files have RAPE22-style columns too, so only the file
            // name keeps them out. Same columns, real file names: all refused.
            foreach (['Oncampushate222324.csv', 'Residencehallhate222324.csv', 'Reportedcrime222324.csv', 'Oncampusarrest222324.csv'] as $name) {
                copy($this->fixture('clery/Oncampuscrime212223.csv'), "{$dir}/{$name}");
                $this->err = [];

                self::assertSame(1, $this->cleryCommand()->handle(["{$dir}/{$name}"]), $name);
                self::assertStringContainsString('hate-crime, arrest, discipline, fire, unfounded and "Reported" files are not imported', implode("\n", $this->err));
            }

            // The real crime file name passes.
            copy($this->fixture('clery/Oncampuscrime212223.csv'), "{$dir}/Oncampuscrime222324.csv");
            self::assertSame(0, $this->cleryCommand()->handle(["{$dir}/Oncampuscrime222324.csv"]), implode("\n", $this->err));
        } finally {
            array_map('unlink', glob("{$dir}/*.csv") ?: []);
            @rmdir($dir);
        }

        self::assertSame(1, $this->rowCount('import_runs'), 'only the crime file was queued');
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

    public function testUtf8NamesAndDuplicateSlugsAreHandled(): void
    {
        $this->importFixtures();

        // hd2023.csv is UTF-8 with a byte-order mark, like the real hd2025.csv.
        $school = Database::connection()->query('SELECT name, slug FROM schools WHERE unitid = 990008')->fetch();
        self::assertSame('Université Fixture de Puerto Rico', $school['name']);
        self::assertSame('universite-fixture-de-puerto-rico', $school['slug']);

        $utica = Database::connection()->query('SELECT slug FROM schools WHERE unitid = 990007')->fetchColumn();
        self::assertSame('fixture-state-university-utica', $utica);
    }

    public function testWindows1252NamesAreConverted(): void
    {
        // Older IPEDS releases were Windows-1252.
        (new \Keel\App\Services\Imports\SchoolImporter())->import($this->fixture('ipeds/HD_windows1252.csv'), null);

        $school = Database::connection()->query('SELECT name, slug FROM schools WHERE unitid = 990009')->fetch();
        self::assertSame('Collège Fixture de Montréal', $school['name']);
        self::assertSame('college-fixture-de-montreal', $school['slug']);
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
        $exit = $this->cleryCommand()->handle([$this->fixture('clery/Oncampuscrime212223.csv'), '2019']);
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
        copy($this->fixture('clery/Oncampuscrime212223.csv'), $copy);

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
        self::assertSame(0, $this->schoolsCommand()->handle([$this->fixture('ipeds/hd2023.csv')]));

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
        $copy = sys_get_temp_dir() . '/hd2023-' . bin2hex(random_bytes(3)) . '.csv';
        copy($this->fixture('ipeds/hd2023.csv'), $copy);

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
