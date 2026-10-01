<?php

declare(strict_types=1);

namespace Tests\Feature;

use Keel\App\Models\SurvivorReport;
use Keel\App\Services\Survivor\CaseDeletionService;
use Keel\App\Services\Survivor\ReportStatsService;
use Keel\App\Support\Config;
use Tests\Support\SurvivorHelpers;
use Tests\TestCase;

/**
 * "What survivors have told us": figures only from 3 counted reports, only
 * approved reports whose authors agreed to be counted, accounts with year,
 * setting and category only, and the national total from 25.
 */
class SurvivorStatsFeatureTest extends TestCase
{
    use SurvivorHelpers;

    private const PAGE = '/schools/ny/fixture-state-university';

    protected function setUp(): void
    {
        parent::setUp();
        $this->importFixtures();
        $this->enableSubmissions();
    }

    public function testBelowTheThresholdOnlyTheCountMessageShows(): void
    {
        $this->makeReport([], 'approved');
        $this->makeReport([], 'approved');

        $page = $this->get(self::PAGE)->body;
        self::assertStringContainsString('What survivors have told us', $page);
        self::assertStringContainsString('Fewer than 3 survivor reports so far.', $page);
        self::assertStringContainsString('Clery figures count reports made to the school. Survivor reports here include assaults that were never reported to the school.', $page);
        self::assertStringNotContainsString('Reported it to the school', $page);
        self::assertStringContainsString('/submit?school=', $page);
    }

    public function testOnlyApprovedCountedReportsMakeUpTheFigures(): void
    {
        // Counted: three approved with consent a or b.
        $this->makeReport(['incident_year' => 2022, 'reported_to_school' => 1, 'school_outcomes' => ['discouraged'], 'response_rating' => 1], 'approved');
        $this->makeReport(['incident_year' => 2023, 'reported_to_school' => 1, 'school_outcomes' => ['investigation_opened'], 'response_rating' => 4, 'consent' => 'stats_and_account'], 'approved');
        $this->makeReport(['incident_year' => 2023, 'reported_to_school' => 0, 'school_channels' => [], 'not_reported_reasons' => ['retaliation', 'school_discouraged']], 'approved');
        // Not counted: waiting, rejected, private, and another school.
        $this->makeReport([], 'submitted');
        $this->makeReport([], 'rejected');
        $this->makeReport(['consent' => 'private'], 'private');
        $this->makeReport(['school_id' => (int) $this->school(990002)['id']], 'approved');

        $stats = (new ReportStatsService())->forSchool((int) $this->school()['id']);
        self::assertSame(3, $stats['count']);
        self::assertTrue($stats['show_stats']);
        self::assertSame([2023 => 2, 2022 => 1], $stats['by_year']);
        self::assertSame(67, $stats['reported_pct']);
        self::assertSame(67, $stats['discouraged_pct'], 'discouraged after reporting, or from reporting at all');
        self::assertSame(2.5, $stats['average_rating']);
        self::assertSame(2, $stats['rating_count']);
        self::assertSame(['retaliation', 'school_discouraged'], array_column($stats['top_reasons'], 'key'));

        $page = $this->get(self::PAGE)->body;
        self::assertStringContainsString('From 3 survivor reports', $page);
        self::assertStringContainsString('67%', $page);
        self::assertStringContainsString('2.5<span class="stat-unit"> of 5</span>', $page);
        self::assertStringNotContainsString('Fewer than 3', $page);
    }

    public function testWithdrawingTakesAReportOutOfTheFiguresAtOnce(): void
    {
        $this->makeReport([], 'approved');
        $this->makeReport([], 'approved');
        $third = $this->makeReport([], 'approved');
        self::assertStringContainsString('From 3 survivor reports', $this->get(self::PAGE)->body);

        (new CaseDeletionService())->delete($third['case_id']);
        self::assertStringContainsString('Fewer than 3 survivor reports so far.', $this->get(self::PAGE)->body);
    }

    public function testPublishedAccountsShowYearSettingAndCategoryOnly(): void
    {
        $admin = $this->createUser();
        $with = $this->makeReport(['consent' => 'stats_and_account', 'incident_season' => 'winter', 'setting' => 'greek_housing', 'perpetrator' => 'student_employee', 'account' => 'Unedited words.'], 'approved');
        SurvivorReport::savePublished($with['report_id'], 'The edited words.', (int) $admin['id']);
        $statsOnly = $this->makeReport(['consent' => 'stats', 'account' => 'Never shown.'], 'approved');
        SurvivorReport::savePublished($statsOnly['report_id'], 'Never shown either.', (int) $admin['id']);

        $page = $this->get(self::PAGE)->body;
        self::assertStringContainsString('The edited words.', $page);
        self::assertStringContainsString('<span class="badge">Fraternity or sorority housing</span>', $page);
        self::assertStringContainsString('<span class="badge">Student employee</span>', $page);
        self::assertStringContainsString('<span class="badge">2023</span>', $page);
        foreach (['Unedited words.', 'Never shown', 'Winter', 'winter', 'Evidence on file'] as $hidden) {
            self::assertStringNotContainsString($hidden, $page, $hidden);
        }
    }

    public function testTheHomePageTotalAppearsFromTheConfiguredNumber(): void
    {
        Config::set('survivor_reports.homepage_min_reports', 3);
        $this->makeReport([], 'approved');
        $this->makeReport(['school_id' => (int) $this->school(990002)['id']], 'approved');
        $this->makeReport(['consent' => 'private'], 'private');

        $home = $this->get('/')->body;
        self::assertStringContainsString('href="/submit"', $home);
        self::assertStringNotContainsString('survivors have told us what happened at their schools', $home);

        $this->makeReport(['school_id' => (int) $this->school(990003)['id']], 'approved');
        self::assertStringContainsString('<strong class="nums">3</strong> survivors have told us what happened at their schools', $this->get('/')->body);
    }

    public function testTheDefaultsAreThreeAndTwentyFive(): void
    {
        Config::reset();
        self::assertSame(3, Config::get('survivor_reports.stats_min_reports'));
        self::assertSame(25, Config::get('survivor_reports.homepage_min_reports'));
        self::assertSame(20 * 1024 * 1024, Config::get('evidence.max_file_bytes'));
        self::assertSame(20, Config::get('evidence.max_files'));
    }
}
