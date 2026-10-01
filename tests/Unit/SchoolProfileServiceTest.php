<?php

declare(strict_types=1);

namespace Tests\Unit;

use Keel\App\Services\SchoolProfileService;
use Keel\App\Support\Format;
use PHPUnit\Framework\TestCase;

class SchoolProfileServiceTest extends TestCase
{
    public function testRateIsPerThousandStudentsAndNeedsEnrollment(): void
    {
        self::assertEqualsWithDelta(0.5882, SchoolProfileService::rate(4, 6800), 0.0001);
        self::assertSame(0.0, SchoolProfileService::rate(0, 6800));
        self::assertNull(SchoolProfileService::rate(4, null));
        self::assertNull(SchoolProfileService::rate(4, 0));
        self::assertNull(SchoolProfileService::rate(null, 6800));
    }

    public function testSizeBandsCoverEveryEnrollment(): void
    {
        self::assertSame('fewer than 1,000 students', SchoolProfileService::sizeBand(0)['label']);
        self::assertSame('fewer than 1,000 students', SchoolProfileService::sizeBand(999)['label']);
        self::assertSame('1,000 to 4,999 students', SchoolProfileService::sizeBand(1000)['label']);
        self::assertSame('5,000 to 14,999 students', SchoolProfileService::sizeBand(5000)['label']);
        self::assertSame('30,000 or more students', SchoolProfileService::sizeBand(250000)['label']);
    }

    public function testComparisonWordsAllowForNoise(): void
    {
        self::assertSame('about the same as', SchoolProfileService::compareWords(1.05, 1.0));
        self::assertSame('higher than', SchoolProfileService::compareWords(1.2, 1.0));
        self::assertSame('lower than', SchoolProfileService::compareWords(0.5, 1.0));
        self::assertSame('the same as', SchoolProfileService::compareWords(0.0, 0.0));
        self::assertNull(SchoolProfileService::compareWords(null, 1.0));
    }

    public function testSlugsAreStableAcrossPhpBuilds(): void
    {
        self::assertSame('universite-fixture-de-puerto-rico', Format::slug('Université Fixture de Puerto Rico'));
        self::assertSame('st-johns-college', Format::slug("St. John's College"));
        self::assertSame('texas-a-and-m-university-commerce', Format::slug('Texas A&M University–Commerce'));
    }
}
