<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Participation\Domain;

use App\ChildrenMinistry\Participation\Domain\ClassAssignment;
use App\ChildrenMinistry\Participation\Domain\Enrolment;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\Shared\Domain\CalendarDate;
use App\Shared\Domain\ErrorKind;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class EnrolmentDomainTest extends TestCase
{
    private const string ID = '11111111-1111-4111-8111-111111111111';

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-06-01T10:00:00Z');
    }

    public function testAnEnrolmentCoversAHalfOpenDateRange(): void
    {
        $enrolment = $this->enrolment('2026-06-01', '2026-07-01');

        self::assertFalse($enrolment->covers(CalendarDate::parse('2026-05-31')));
        self::assertTrue($enrolment->covers(CalendarDate::parse('2026-06-01')), 'the first day is included');
        self::assertTrue($enrolment->covers(CalendarDate::parse('2026-06-30')));
        self::assertFalse($enrolment->covers(CalendarDate::parse('2026-07-01')), 'the end date is exclusive');
    }

    public function testRangesMustEndAfterTheyStart(): void
    {
        foreach ([['2026-06-01', '2026-06-01'], ['2026-06-02', '2026-06-01']] as [$from, $until]) {
            try {
                $this->enrolment($from, $until);
                self::fail("Accepted $from to $until");
            } catch (ChildrenException $error) {
                self::assertSame(ErrorKind::Invalid, $error->kind());
            }
        }
    }

    public function testEndingRecordsTheTimeBumpsTheVersionAndStopsCoverage(): void
    {
        $enrolment = $this->enrolment('2026-06-01', '2026-07-01');

        $ended = $enrolment->end(0, $this->now->modify('+1 day'));

        self::assertSame(1, $ended->version);
        self::assertEquals($this->now->modify('+1 day'), $ended->endedAt);
        self::assertFalse($ended->covers(CalendarDate::parse('2026-06-15')), 'an ended enrolment covers nothing');
        self::assertNull($enrolment->endedAt, 'the original is untouched');
    }

    public function testEndingChecksTheVersionAndHappensOnce(): void
    {
        $ended = $this->enrolment('2026-06-01', '2026-07-01')->end(0, $this->now);

        foreach ([fn () => $ended->end(0, $this->now), fn () => $ended->end(1, $this->now), fn () => $this->enrolment('2026-06-01', '2026-07-01')->end(3, $this->now)] as $attempt) {
            try {
                $attempt();
                self::fail('Expected a conflict.');
            } catch (ChildrenException $error) {
                self::assertSame(ErrorKind::Conflict, $error->kind());
            }
        }
    }

    public function testAnAlreadyEndedParticipationReportsThat(): void
    {
        $ended = $this->enrolment('2026-06-01', '2026-07-01')->end(0, $this->now);

        $this->expectExceptionMessage('This participation already ended.');
        $ended->end(1, $this->now);
    }

    public function testClassAssignmentsFollowTheSameRules(): void
    {
        $assignment = ClassAssignment::create(self::ID, self::ID, self::ID, self::ID, self::ID, CalendarDate::parse('2026-06-01'), CalendarDate::parse('2026-07-01'));

        self::assertTrue($assignment->covers(CalendarDate::parse('2026-06-30')));
        self::assertFalse($assignment->covers(CalendarDate::parse('2026-07-01')));
        $ended = $assignment->end(0, $this->now);
        self::assertSame(1, $ended->version);
        self::assertFalse($ended->covers(CalendarDate::parse('2026-06-15')));
        $this->expectException(ChildrenException::class);
        $ended->end(1, $this->now);
    }

    private function enrolment(string $from, string $until): Enrolment
    {
        return Enrolment::create(self::ID, self::ID, self::ID, CalendarDate::parse($from), CalendarDate::parse($until), $this->now);
    }
}
