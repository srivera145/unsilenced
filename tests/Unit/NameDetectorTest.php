<?php

declare(strict_types=1);

namespace Tests\Unit;

use Keel\App\Support\NameDetector;
use PHPUnit\Framework\TestCase;

class NameDetectorTest extends TestCase
{
    public function testFlagsFullNames(): void
    {
        self::assertSame(['John Smith'], NameDetector::find('The lawsuit names John Smith and the university.'));
        self::assertSame(['Mary-Kate O\'Neil'], NameDetector::find('A complaint by Mary-Kate O\'Neil was dismissed.'));
        self::assertSame(['Jane Q. Public'], NameDetector::find('Investigators interviewed Jane Q. Public in March.'));
        self::assertContains('José Núñez', NameDetector::find('According to José Núñez, the review is ongoing.'));
    }

    public function testFindsNamesInsideInstitutionalPhrases(): void
    {
        self::assertSame(['Jane Doe'], NameDetector::find('Attorney General Jane Doe announced a review.'));
        self::assertContains('Dr. Smith', NameDetector::find('The panel was chaired by Dr. Smith.'));
        self::assertContains('Coach Miller', NameDetector::find('Coach Miller was placed on leave.'));
        self::assertContains('Dean Smith', NameDetector::find('Dean Smith resigned after the review.'));
        self::assertContains('Judge Alvarez', NameDetector::find('The ruling by Judge Alvarez was appealed.'));
    }

    public function testIgnoresInstitutionsDatesAndGreekLetters(): void
    {
        self::assertSame([], NameDetector::find('The U.S. Department of Education Office for Civil Rights opened a Title IX investigation in March 2024.'));
        self::assertSame([], NameDetector::find('The Chi Phi chapter was suspended.'));
        self::assertSame([], NameDetector::find('A state review by the Board of Regents is ongoing.'));
    }

    public function testIgnoresTheSchoolsOwnName(): void
    {
        self::assertSame(['Ithaca Tech'], NameDetector::find('Ithaca Tech settled the case.'));
        self::assertSame([], NameDetector::find('Ithaca Tech settled the case.', ['Ithaca', 'Tech']));
    }
}
