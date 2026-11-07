<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Participation\Application;

use App\ChildrenMinistry\Participation\Application\Command\AssignClass\AssignClassCommand;
use App\ChildrenMinistry\Participation\Application\Command\AssignClass\AssignClassHandler;
use App\ChildrenMinistry\Participation\Application\Command\EndClassAssignment\EndClassAssignmentCommand;
use App\ChildrenMinistry\Participation\Application\Command\EndClassAssignment\EndClassAssignmentHandler;
use App\ChildrenMinistry\Participation\Application\Command\EndEnrolment\EndEnrolmentCommand;
use App\ChildrenMinistry\Participation\Application\Command\EndEnrolment\EndEnrolmentHandler;
use App\ChildrenMinistry\Participation\Application\Command\EnrolChild\EnrolChildCommand;
use App\ChildrenMinistry\Participation\Application\Command\EnrolChild\EnrolChildHandler;
use App\ChildrenMinistry\Participation\Application\EnrolmentView;
use App\ChildrenMinistry\Participation\Domain\ClassAssignment;
use App\ChildrenMinistry\Participation\Domain\Enrolment;
use App\ChildrenMinistry\Participation\Domain\MinistryClass;
use App\ChildrenMinistry\Shared\Application\ParticipantAccess;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Domain\IdentityException;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Domain\CalendarDate;
use App\Tests\ChildrenMinistry\Shared\CallLog;
use App\Tests\ChildrenMinistry\Shared\RecordingChildLocks;
use App\Tests\ChildrenMinistry\Shared\RecordingPeople;
use App\Tests\People\Application\SequentialIds;
use App\Tests\Support\AllowedPermissions;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * The order of operations is the safety property here: permission first, then the child's person and guard rows,
 * and only then the enrolment or placement row. Each test asserts the exact sequence of reads, locks and writes.
 */
final class EnrolmentHandlersTest extends TestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string CHILD = '11111111-1111-4111-8111-111111111111';
    private const string PROGRAMME = '22222222-2222-4222-8222-222222222222';
    private const string ENROLMENT = '33333333-3333-4333-8333-333333333333';
    private const string GROUP = '44444444-4444-4444-8444-444444444444';
    private const string ASSIGNMENT = '55555555-5555-4555-8555-555555555555';

    private CallLog $log;
    private RecordingParticipationRepository $repository;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->log = new CallLog();
        $this->repository = new RecordingParticipationRepository($this->log);
        $this->clock = new MockClock('2026-06-01 10:00:00 UTC');
        $enrolment = $this->enrolment();
        $this->repository->enrolments[self::ENROLMENT] = $enrolment;
        $this->repository->classes[self::GROUP] = MinistryClass::create(self::GROUP, self::PROGRAMME, 'Juniors', 60, 156);
        $this->repository->assignments[self::ASSIGNMENT] = ClassAssignment::create(self::ASSIGNMENT, self::PROGRAMME, self::GROUP, self::CHILD, self::ENROLMENT, CalendarDate::parse('2026-06-01'), CalendarDate::parse('2026-07-01'));
    }

    public function testEnrollingTakesTheChildLocksBeforeAnythingElseThenWritesAndAudits(): void
    {
        $this->enrolHandler()->handle($this->enrolCommand());

        self::assertSame(['person:'.self::CHILD, 'guard:'.self::CHILD, 'read:programme', 'insert:enrolment', 'audit:child.enrolled'], $this->log->entries);
    }

    public function testWithoutPermissionNothingIsReadLockedOrWritten(): void
    {
        $this->expectException(IdentityException::class);
        try {
            $this->enrolHandler(granted: ['children.read'])->handle($this->enrolCommand());
        } finally {
            self::assertSame([], $this->log->entries);
        }
    }

    public function testAnAdultIsRefusedAfterLockingButBeforeWriting(): void
    {
        try {
            $this->enrolHandler(birth: '1980-01-01')->handle($this->enrolCommand());
            self::fail('Expected a rejection.');
        } catch (ChildrenException $error) {
            self::assertSame('A recorded birth date identifying a child under 18 is required.', $error->getMessage());
        }

        self::assertSame(['person:'.self::CHILD, 'guard:'.self::CHILD, 'read:programme'], $this->log->entries);
    }

    public function testAnUnknownProgrammeStopsBeforeTheBirthDateIsEvenChecked(): void
    {
        $this->expectExceptionMessage('Programme was not found.');

        $this->enrolHandler(programmes: [], birth: '1980-01-01')->handle($this->enrolCommand());
    }

    public function testEndingAnEnrolmentReadsThenLocksTheChildThenRereadsForUpdate(): void
    {
        $this->endEnrolmentHandler()->handle(new EndEnrolmentCommand(self::ACTOR, self::ENROLMENT, 0));

        self::assertSame(
            ['read:enrolment', 'person:'.self::CHILD, 'guard:'.self::CHILD, 'forUpdate:enrolment', 'update:enrolment', 'endAssignments', 'audit:enrolment.ended'],
            $this->log->entries,
        );
        self::assertNotNull($this->repository->enrolments[self::ENROLMENT]->endedAt);
    }

    public function testAStaleVersionEndsNothingAndAuditsNothing(): void
    {
        try {
            $this->endEnrolmentHandler()->handle(new EndEnrolmentCommand(self::ACTOR, self::ENROLMENT, 4));
            self::fail('Expected a conflict.');
        } catch (ChildrenException $error) {
            self::assertSame('This record changed. Reload before trying again.', $error->getMessage());
        }

        self::assertSame(['read:enrolment', 'person:'.self::CHILD, 'guard:'.self::CHILD, 'forUpdate:enrolment'], $this->log->entries);
        self::assertNull($this->repository->enrolments[self::ENROLMENT]->endedAt);
    }

    public function testEndingAnUnknownEnrolmentLocksNothing(): void
    {
        $this->expectExceptionMessage('Enrolment was not found.');
        try {
            $this->endEnrolmentHandler()->handle(new EndEnrolmentCommand(self::ACTOR, '66666666-6666-4666-8666-666666666666', 0));
        } finally {
            self::assertSame(['read:enrolment'], $this->log->entries);
        }
    }

    public function testAssigningAClassLocksTheChildBeforeLoadingTheEnrolmentAndTheClassForUpdate(): void
    {
        $this->assignHandler()->handle(new AssignClassCommand(self::ACTOR, self::ENROLMENT, self::GROUP, CalendarDate::parse('2026-06-01'), CalendarDate::parse('2026-07-01')));

        self::assertSame(
            ['read:enrolment', 'person:'.self::CHILD, 'guard:'.self::CHILD, 'forUpdate:enrolment', 'find:class', 'insert:assignment', 'audit:class.assigned'],
            $this->log->entries,
        );
    }

    public function testAPlacementOutsideTheEnrolmentIsRefusedBeforeWriting(): void
    {
        try {
            $this->assignHandler()->handle(new AssignClassCommand(self::ACTOR, self::ENROLMENT, self::GROUP, CalendarDate::parse('2026-05-01'), CalendarDate::parse('2026-07-01')));
            self::fail('Expected a rejection.');
        } catch (ChildrenException $error) {
            self::assertSame('Class assignment must belong to and fit within an active enrolment.', $error->getMessage());
        }

        self::assertNotContains('insert:assignment', $this->log->entries);
        self::assertNotContains('audit:class.assigned', $this->log->entries);
    }

    public function testEndingAPlacementFollowsTheSameLockOrder(): void
    {
        $this->endAssignmentHandler()->handle(new EndClassAssignmentCommand(self::ACTOR, self::ASSIGNMENT, 0));

        self::assertSame(
            ['read:assignment', 'person:'.self::CHILD, 'guard:'.self::CHILD, 'forUpdate:assignment', 'update:assignment', 'audit:classassignment.ended'],
            $this->log->entries,
        );
    }

    /**
     * @param list<string>|null $programmes
     * @param list<string>      $granted
     */
    private function enrolHandler(?array $programmes = null, string $birth = '2016-03-01', array $granted = ['children.manage']): EnrolChildHandler
    {
        return new EnrolChildHandler(
            new AllowedPermissions($granted),
            new RecordingChildrenAudit($this->log),
            $this->clock,
            new SequentialIds(),
            $this->repository,
            new RecordingParticipationReader($this->log, $programmes ?? [self::PROGRAMME]),
            $this->access($birth),
            new ChurchCalendar($this->clock, 'Africa/Johannesburg'),
        );
    }

    private function endEnrolmentHandler(): EndEnrolmentHandler
    {
        return new EndEnrolmentHandler(
            new AllowedPermissions(['children.manage']),
            new RecordingChildrenAudit($this->log),
            $this->clock,
            $this->repository,
            new RecordingParticipationReader($this->log, enrolments: [self::ENROLMENT => $this->enrolmentView()]),
            $this->access('2016-03-01'),
        );
    }

    private function assignHandler(): AssignClassHandler
    {
        return new AssignClassHandler(
            new AllowedPermissions(['children.manage']),
            new RecordingChildrenAudit($this->log),
            $this->clock,
            new SequentialIds(),
            $this->repository,
            new RecordingParticipationReader($this->log, enrolments: [self::ENROLMENT => $this->enrolmentView()]),
            $this->access('2016-03-01'),
        );
    }

    private function endAssignmentHandler(): EndClassAssignmentHandler
    {
        $assignment = $this->repository->assignments[self::ASSIGNMENT];

        return new EndClassAssignmentHandler(
            new AllowedPermissions(['children.manage']),
            new RecordingChildrenAudit($this->log),
            $this->clock,
            $this->repository,
            new RecordingParticipationReader($this->log, assignments: [self::ASSIGNMENT => new \App\ChildrenMinistry\Participation\Application\AssignmentView(
                $assignment->id,
                $assignment->programmeId,
                $assignment->classId,
                $assignment->childId,
                $assignment->enrolmentId,
                $assignment->validFrom,
                $assignment->validUntil,
                null,
                0,
            )]),
            $this->access('2016-03-01'),
        );
    }

    private function access(string $birth): ParticipantAccess
    {
        return new ParticipantAccess(new RecordingPeople($this->log, [self::CHILD => true], [self::CHILD => $birth]), new RecordingChildLocks($this->log));
    }

    private function enrolCommand(): EnrolChildCommand
    {
        return new EnrolChildCommand(self::ACTOR, self::PROGRAMME, self::CHILD, CalendarDate::parse('2026-06-01'), CalendarDate::parse('2027-06-01'));
    }

    private function enrolment(): Enrolment
    {
        return Enrolment::create(self::ENROLMENT, self::PROGRAMME, self::CHILD, CalendarDate::parse('2026-06-01'), CalendarDate::parse('2026-07-01'), new DateTimeImmutable('2026-05-01T00:00:00Z'));
    }

    private function enrolmentView(): EnrolmentView
    {
        return new EnrolmentView(self::ENROLMENT, self::PROGRAMME, self::CHILD, CalendarDate::parse('2026-06-01'), CalendarDate::parse('2026-07-01'), null, 0, new DateTimeImmutable('2026-05-01T00:00:00Z'));
    }
}
