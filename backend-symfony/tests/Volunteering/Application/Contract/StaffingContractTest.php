<?php

declare(strict_types=1);

namespace App\Tests\Volunteering\Application\Contract;

use App\Volunteering\Application\Contract\ServingActivity;
use App\Volunteering\Application\Contract\SourceActivity;
use App\Volunteering\Application\Contract\StaffingAssessment;
use App\Volunteering\Application\Contract\StaffingUnavailable;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class StaffingContractTest extends TestCase
{
    public function testStaffingUnavailableCarriesTheJavaMessage(): void
    {
        self::assertSame('Volunteer staffing is unavailable.', new StaffingUnavailable()->getMessage());
    }

    public function testAssessmentExposesReadinessAndIssues(): void
    {
        $assessment = new StaffingAssessment('a', 'p', 'Sam', 'LEAD', false, ['Credential expired.']);

        self::assertFalse($assessment->ready);
        self::assertSame(['Credential expired.'], $assessment->issues);
    }

    public function testServingActivityRequiresKindAndId(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ServingActivity('', 'x');
    }

    public function testSourceActivityDefaultsOptionalScheduleDetailsToNull(): void
    {
        $now = new DateTimeImmutable('2026-11-06T09:00:00Z');
        $activity = new SourceActivity('id', 'Sunday', $now, $now->modify('+1 hour'), true);

        self::assertNull($activity->scheduleRevision);
        self::assertNull($activity->venueLabel);
    }
}
