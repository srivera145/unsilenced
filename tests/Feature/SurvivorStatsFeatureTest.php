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
        self::assertSame(67, $stats['reported_pct'], 'all three answered: enough');
        self::assertSame(67, $stats['discouraged_pct'], 'discouraged after reporting, or from reporting at all');

        // Phase 2.1: each of these has fewer than 3 responses of its own.
        self::assertNull($stats['average_rating'], 'two ratings');
        self::assertNull($stats['rating_count']);
        self::assertSame([], $stats['top_reasons'], 'each reason given once');
        self::assertTrue($stats['reasons_left_out']);
        self::assertNull($stats['not_reported'], 'one person did not report');
        self::assertNull($stats['by_year'], 'two in 2023, one in 2022');

        $page = $this->get(self::PAGE)->body;
        self::assertStringContainsString('From 3 survivor reports', $page);
        self::assertStringContainsString('67%', $page);
        self::assertSame(3, substr_count($page, 'Not enough responses yet.'), 'rating, years, reasons');
        self::assertStringNotContainsString('of 5</span>', $page);
        self::assertStringNotContainsString('From 2 ratings', $page);
        self::assertStringNotContainsString('I was afraid of retaliation', $page);
        self::assertStringNotContainsString('the 1 person', $page);
    }

    /** Phase 2.1: every figure is checked on its own, at 2 (hidden) and at 3 (shown). */
    public function testEachFigureNeedsThreeResponsesOfItsOwn(): void
    {
        $school = (int) $this->school()['id'];
        $stats = static fn (): array => (new ReportStatsService())->forSchool($school);

        // Five reports: two rated, two not reporting with one shared reason, years 2024 x2.
        $this->makeReport(['incident_year' => 2024, 'reported_to_school' => 1, 'response_rating' => 2], 'approved');
        $this->makeReport(['incident_year' => 2024, 'reported_to_school' => 1, 'response_rating' => 3], 'approved');
        $this->makeReport(['incident_year' => 2022, 'reported_to_school' => 1], 'approved');
        $this->makeReport(['incident_year' => 2021, 'reported_to_school' => 0, 'school_channels' => [], 'not_reported_reasons' => ['not_believed', 'retaliation']], 'approved');
        $this->makeReport(['incident_year' => 2020, 'reported_to_school' => 0, 'school_channels' => [], 'not_reported_reasons' => ['not_believed']], 'approved');

        $below = $stats();
        self::assertNull($below['average_rating'], '2 ratings');
        self::assertNull($below['rating_count']);
        self::assertSame([], $below['top_reasons'], '"not believed" given by 2');
        self::assertNull($below['not_reported'], '2 did not report');
        self::assertNull($below['by_year'], 'no year has 3');

        // One more of each: a third rating, a third "not believed", a third 2024.
        $this->makeReport(['incident_year' => 2024, 'reported_to_school' => 1, 'response_rating' => 4], 'approved');
        $this->makeReport(['incident_year' => 2023, 'reported_to_school' => 0, 'school_channels' => [], 'not_reported_reasons' => ['not_believed']], 'approved');

        $at = $stats();
        self::assertSame(3.0, $at['average_rating']);
        self::assertSame(3, $at['rating_count']);
        self::assertSame([['key' => 'not_believed', 'label' => 'I was afraid I would not be believed', 'count' => 3]], $at['top_reasons']);
        self::assertTrue($at['reasons_left_out'], '"retaliation" (1) is left out');
        self::assertSame(3, $at['not_reported']);
        self::assertSame([['label' => '2024', 'count' => 3], ['label' => 'Earlier years', 'count' => 4]], $at['by_year'], '2020-2023 grouped: 4');

        $page = $this->get(self::PAGE)->body;
        self::assertStringContainsString('3.0<span class="stat-unit"> of 5</span>', $page);
        self::assertStringContainsString('From 3 ratings', $page);
        self::assertStringContainsString('the 3 people who did not', $page);
        self::assertStringContainsString('I was afraid I would not be believed <span class="text-muted nums">(3)</span>', $page);
        self::assertStringNotContainsString('I was afraid of retaliation', $page);
        self::assertStringContainsString('Reasons given by fewer than 3 people are not shown.', $page);
        self::assertStringContainsString('<th scope="row">Earlier years</th><td class="text-end nums">4</td>', $page);
        self::assertStringNotContainsString('<th scope="row">2022</th>', $page);
    }

    public function testSmallYearsAreGroupedAndAShortGroupIsNotCounted(): void
    {
        foreach ([2024, 2024, 2024, 2025, 2021] as $year) {
            $this->makeReport(['incident_year' => $year], 'approved');
        }

        $rows = (new ReportStatsService())->forSchool((int) $this->school()['id'])['by_year'];
        self::assertSame([['label' => '2024', 'count' => 3], ['label' => 'Other years', 'count' => null]], $rows, '2025 is not earlier than 2024');

        $page = $this->get(self::PAGE)->body;
        self::assertStringContainsString('<th scope="row">Other years</th><td class="text-end nums">Fewer than 3</td>', $page);
        self::assertStringNotContainsString('<th scope="row">2025</th>', $page);
        self::assertStringNotContainsString('<th scope="row">2021</th>', $page);
    }

    /** No count under 3 anywhere in the section, whatever the mix of answers. */
    public function testTheSectionNeverPrintsACountUnderThree(): void
    {
        $this->makeReport(['incident_year' => 2023, 'reported_to_school' => 0, 'school_channels' => [], 'not_reported_reasons' => ['not_believed', 'didnt_know_how']], 'approved');
        $this->makeReport(['incident_year' => 2023, 'reported_to_school' => 0, 'school_channels' => [], 'not_reported_reasons' => ['not_believed']], 'approved');
        $this->makeReport(['incident_year' => 2022, 'reported_to_school' => 1, 'response_rating' => 1], 'approved');

        $page = $this->get(self::PAGE)->body;
        preg_match('#<section class="stack stack-6" aria-labelledby="survivors-title">.*?</section>\s*<div class="card card-brand-soft">#s', $page, $section);
        $html = $section[0];

        self::assertDoesNotMatchRegularExpression('/\((?:1|2)\)/', $html, 'no "(1)" or "(2)" after a reason');
        self::assertDoesNotMatchRegularExpression('/\bthe (?:1 person|2 people)\b/', $html);
        self::assertDoesNotMatchRegularExpression('/From [12] ratings?\b/', $html);
        self::assertDoesNotMatchRegularExpression('#<td class="text-end nums">[12]</td>#', $html);
    }

    public function testTheHomeTotalNeverAppearsBelowTheFigureMinimum(): void
    {
        Config::set('survivor_reports.homepage_min_reports', 1);
        $this->makeReport([], 'approved');
        $this->makeReport([], 'approved');

        self::assertStringNotContainsString('survivors have told us what happened at their schools', $this->get('/')->body, 'a setting of 1 is raised to the minimum of 3');

        $this->makeReport([], 'approved');
        self::assertStringContainsString('<strong class="nums">3</strong> survivors have told us', $this->get('/')->body);
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
