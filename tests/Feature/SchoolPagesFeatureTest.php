<?php

declare(strict_types=1);

namespace Tests\Feature;

use Keel\App\Models\AccountabilityItem;
use Keel\App\Models\School;
use Tests\TestCase;

class SchoolPagesFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importFixtures();
    }

    public function testSchoolWithDataShowsCountsRatesChartsSourcesAndSchemaOrg(): void
    {
        $response = $this->get('/schools/ny/fixture-state-university');
        $body = $response->body;

        self::assertSame(200, $response->status);
        self::assertStringContainsString('<title>Fixture State University · Unsilenced</title>', $body);
        self::assertStringContainsString('At a glance: 2023, all Clery locations', $body);
        self::assertStringContainsString('"@type":"CollegeOrUniversity"', $body);
        self::assertStringContainsString('"value":"990001"', $body);

        // 2023 rape: 9 on campus + 2 noncampus + 0 public property = 11. The 7
        // in student housing is part of on campus and must not be added again.
        self::assertMatchesRegularExpression('#<dt class="stat-label">Rape</dt>\s*<dd class="stat-value">11</dd>#', $body);

        // Sex offenses 2023: rape 11 + fondling 6 = 17; 17 / 24,000 x 1,000 = 0.71.
        self::assertStringContainsString('0.71', $body);
        self::assertSame(7, substr_count($body, 'class="chart-svg"'), 'one small-multiple per offense');
        self::assertStringContainsString('2021: 9; 2022: 12; 2023: 11.', $body, 'rape totals per year in the chart description');

        // Every statistic block names its source and year.
        self::assertStringContainsString('Campus Safety and Security Survey (Clery Act data), calendar year 2023.', $body);
        self::assertStringContainsString('Campus Safety and Security Survey (Clery Act data), calendar year 2021–2023.', $body);
        self::assertStringContainsString('IPEDS, 2023 total enrollment.', $body);

        self::assertStringNotContainsString('About the zero', $body);
    }

    public function testSizeGroupWithTooFewSchoolsIsNotPresentedAsAComparison(): void
    {
        // Only one fixture school has 15,000 to 29,999 students: this one.
        $body = $this->get('/schools/ny/fixture-state-university')->body;

        self::assertStringContainsString('Too few schools to compare', $body);
        self::assertStringContainsString('0.58 <span class="text-muted text-xs">(4 schools)</span>', $body, 'New York: 21 reports / 36,100 students');
    }

    public function testSchoolWithNoCleryDataSaysSoPlainly(): void
    {
        $response = $this->get('/schools/tx/fixture-college-of-the-arts');

        self::assertSame(200, $response->status);
        self::assertStringContainsString('No Clery data imported for this school yet', $response->body);
        self::assertStringNotContainsString('class="chart-svg"', $response->body);
        self::assertStringContainsString('1,500 students', $response->body);
    }

    public function testContextNoteShowsForLargeSchoolWithZeroRapesOnly(): void
    {
        $poly = $this->get('/schools/ny/fixture-polytechnic-institute')->body;
        self::assertStringContainsString('About the zero in 2022 and 2023', $poly);
        self::assertStringContainsString('it is not a finding about this school', $poly);
        self::assertStringContainsString('can reflect underreporting', $poly);

        // Zero rapes but 3,200 students: below the threshold.
        self::assertStringNotContainsString('About the zero', $this->get('/schools/ny/fixture-community-college')->body);

        // 5,200 students in a territory, zero in 2023 only.
        self::assertStringContainsString('About the zero in 2023', $this->get('/schools/pr/universite-fixture-de-puerto-rico')->body);
    }

    public function testCopyNeverAccusesSchools(): void
    {
        foreach (['/schools/ny/fixture-polytechnic-institute', '/schools/ny/fixture-state-university', '/methodology', '/'] as $path) {
            $text = strtolower(strip_tags($this->get($path)->body));
            foreach (['hiding', 'hid ', 'covering up', 'cover-up', 'cover up', 'concealed', 'concealing', 'lying', 'fail to report', 'failed to report'] as $phrase) {
                self::assertStringNotContainsString($phrase, $text, "{$path} contains '{$phrase}'");
            }
        }
    }

    public function testSchoolWithoutEnrollmentShowsCountsButNoRate(): void
    {
        $body = $this->get('/schools/oh/fixture-tech')->body;

        self::assertStringContainsString('No rate is shown because this school has no enrollment figure', $body);
        self::assertStringContainsString('class="chart-svg"', $body);
    }

    public function testOnlyPublishedAccountabilityItemsShowAndOutputIsEscaped(): void
    {
        $school = School::findByUnitid(990001);
        AccountabilityItem::create([
            'school_id' => $school['id'], 'type' => 'ocr_investigation', 'item_date' => '2024-03-01',
            'summary' => 'Federal investigation opened <script>alert(1)</script> into the handling of a complaint.',
            'status' => 'open', 'source_name' => 'Office for Civil Rights', 'source_url' => 'https://example.org/ocr', 'is_published' => 1,
        ]);
        AccountabilityItem::create([
            'school_id' => $school['id'], 'type' => 'lawsuit', 'item_date' => '2024-05-01',
            'summary' => 'DRAFT ITEM NOT YET PUBLISHED', 'status' => 'open', 'source_name' => 'Court', 'source_url' => 'https://example.org/court', 'is_published' => 0,
        ]);

        $body = $this->get('/schools/ny/fixture-state-university')->body;

        self::assertStringContainsString('Federal Title IX investigation (OCR)', $body);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
        self::assertStringNotContainsString('<script>alert(1)</script>', $body);
        self::assertStringNotContainsString('DRAFT ITEM NOT YET PUBLISHED', $body);
        self::assertStringContainsString('href="https://example.org/ocr" rel="noopener noreferrer nofollow"', $body);
    }

    public function testSearchFindsByNameAndCityAndTreatsWildcardsLiterally(): void
    {
        $byName = $this->get('/schools?q=polytech')->body;
        self::assertStringContainsString('Fixture Polytechnic Institute', $byName);
        self::assertStringContainsString('1 school matching', $byName);

        self::assertStringContainsString('Fixture University, Houston', $this->get('/schools?q=houston')->body);
        self::assertStringContainsString('0 schools matching', $this->get('/schools?q=%25')->body);

        $state = $this->get('/schools/ny');
        self::assertSame(200, $state->status);
        self::assertStringContainsString('Schools in New York', $state->body);
        self::assertStringContainsString('4 schools', $state->body);
    }

    public function testUnknownSchoolsAndNonCanonicalUrlsAre404(): void
    {
        self::assertSame(404, $this->get('/schools/ny/no-such-school')->status);
        self::assertSame(404, $this->get('/schools/NY/fixture-state-university')->status);
        self::assertSame(404, $this->get('/schools/zz')->status);
        self::assertSame(404, $this->get('/schools/tx/fixture-state-university')->status);
    }
}
