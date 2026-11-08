<?php

declare(strict_types=1);

namespace App\Tests\Volunteering\Application\Roster;

use App\IdentityAccess\Domain\IdentityException;
use App\Tests\Giving\Application\RecordingPermissions;
use App\Tests\People\Application\SequentialIds;
use App\Tests\Volunteering\Application\Teams\JournalActivityLocks;
use App\Tests\Volunteering\Application\Teams\JournalPeople;
use App\Tests\Volunteering\Application\Teams\LockJournal;
use App\Tests\Volunteering\Application\Teams\MemoryServingAudit;
use App\Tests\Volunteering\Application\Teams\MemoryTeams;
use App\Tests\Volunteering\Application\Teams\MemoryVolunteers;
use App\Tests\Volunteering\Application\Teams\StaticLinkedActivities;
use App\Volunteering\Application\ActivityRegistry;
use App\Volunteering\Application\Command\AssignVolunteer\AssignVolunteerCommand;
use App\Volunteering\Application\Command\AssignVolunteer\AssignVolunteerHandler;
use App\Volunteering\Application\Command\ChangeVolunteerAssignment\ChangeVolunteerAssignmentCommand;
use App\Volunteering\Application\Command\ChangeVolunteerAssignment\ChangeVolunteerAssignmentHandler;
use App\Volunteering\Application\Contract\ServingActivity;
use App\Volunteering\Application\RosterPlanning;
use App\Volunteering\Application\VolunteerLocks;
use App\Volunteering\Domain\Assignment;
use App\Volunteering\Domain\Invitation;
use App\Volunteering\Domain\Shift;
use App\Volunteering\Domain\VolunteerException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Roster commands in isolation: permission before any lock, the documented lock order (person, linked activities,
 * volunteer row, then shift, assignment and invitation rows), stale versions refused without writes, audit on success.
 */
final class RosterHandlersTest extends TestCase
{
    private const string ACTOR = '00000000-0000-4000-8000-0000000000ac';
    private const string VOLUNTEER = '00000000-0000-4000-8000-0000000000b1';
    private const string PERSON = '00000000-0000-4000-8000-0000000000c1';
    private const string SHIFT = '00000000-0000-4000-8000-0000000000d1';
    private const string ASSIGNMENT = '00000000-0000-4000-8000-0000000000e1';
    private const string INVITATION = '00000000-0000-4000-8000-0000000000f1';

    private LockJournal $journal;
    private MemoryRoster $roster;
    private MemoryServingAudit $audit;
    private RecordingDutyNotices $notices;
    private RecordingPermissions $auth;
    private RosterPlanning $planning;

    protected function setUp(): void
    {
        $this->journal = new LockJournal();
        $this->roster = new MemoryRoster($this->journal);
        $this->roster->shifts[self::SHIFT] = Shift::reconstitute(self::SHIFT, 'r', 'h', 'team', 'role', 'STANDALONE', null, null, new DateTimeImmutable('2030-01-01T08:00:00Z'), new DateTimeImmutable('2030-01-01T10:00:00Z'), 2, 'OPEN', 3, 0);
        $this->audit = new MemoryServingAudit();
        $this->notices = new RecordingDutyNotices();
        $this->auth = new RecordingPermissions(self::ACTOR, ['volunteering.manage']);
        $volunteers = new MemoryVolunteers($this->journal, [self::VOLUNTEER => self::PERSON]);
        $locks = new VolunteerLocks($volunteers, new JournalPeople($this->journal), new StaticLinkedActivities([new ServingActivity('EVENT_OCCURRENCE', 'z'), new ServingActivity('CHILDREN_SESSION', 'a')]), $volunteers, [new JournalActivityLocks($this->journal, 'children')]);
        $this->planning = new RosterPlanning($this->roster, $this->roster, new ActiveTeams(), new MemoryTeams($this->journal), $locks, $volunteers, new ActivityRegistry([]), $this->audit, $this->notices, new SequentialIds(), new MockClock('2029-12-01T00:00:00Z'));
    }

    private function assign(string $actor = self::ACTOR, int $version = 3): string
    {
        return new AssignVolunteerHandler($this->auth, $this->planning)->handle(new AssignVolunteerCommand($actor, '00000000-0000-4000-8000-000000000999', self::SHIFT, $version, self::VOLUNTEER, 'PROPOSED'));
    }

    public function testAssignLocksPersonActivitiesVolunteerThenShift(): void
    {
        $id = $this->assign();

        self::assertSame(['person:'.self::PERSON, 'children:CHILDREN_SESSION:a', 'children:EVENT_OCCURRENCE:z', 'volunteer:'.self::VOLUNTEER, 'shift:'.self::SHIFT], $this->journal->entries);
        self::assertSame(['assignment:'.$id, 'shift:'.self::SHIFT, 'event:ASSIGNED'], $this->roster->writes);
        self::assertSame(4, $this->roster->shifts[self::SHIFT]->version);
        self::assertSame(['assignment.created:'.$id], $this->audit->rows);
        self::assertSame(['assigned:'.$id], $this->notices->notices);
    }

    public function testPermissionIsCheckedBeforeAnyLock(): void
    {
        try {
            $this->assign('00000000-0000-4000-8000-0000000000ff');
            self::fail('A spoofed actor must be refused.');
        } catch (IdentityException) {
            self::assertSame([], $this->journal->entries);
        }
        $this->auth = new RecordingPermissions(self::ACTOR, ['volunteering.read']);
        $this->expectException(IdentityException::class);
        try {
            $this->assign();
        } finally {
            self::assertSame([], $this->journal->entries);
            self::assertSame([], $this->roster->writes);
        }
    }

    public function testAStaleShiftVersionWritesNothing(): void
    {
        try {
            $this->assign(version: 2);
            self::fail('A stale version must be refused.');
        } catch (VolunteerException $conflict) {
            self::assertSame('This record changed. Refresh before saving.', $conflict->getMessage());
        }
        self::assertSame([], $this->roster->writes);
        self::assertSame([], $this->audit->rows);
        self::assertSame([], $this->notices->notices);
    }

    public function testAStatusChangeLocksVolunteerShiftAssignmentThenInvitationsAndWithdrawsThem(): void
    {
        $this->roster->assignments[self::ASSIGNMENT] = Assignment::reconstitute(self::ASSIGNMENT, 'r', 'h', self::SHIFT, self::VOLUNTEER, 'PROPOSED', 1, self::ACTOR, new DateTimeImmutable('2029-11-01T00:00:00Z'));
        $this->roster->invitations[self::INVITATION] = Invitation::issue(self::INVITATION, self::ASSIGNMENT, 1, 0, new DateTimeImmutable('2030-01-01T08:00:00Z'));
        $handler = new ChangeVolunteerAssignmentHandler($this->auth, $this->planning);

        $handler->handle(new ChangeVolunteerAssignmentCommand(self::ACTOR, self::ASSIGNMENT, 1, 'CONFIRMED', 'Phoned'));

        self::assertSame(['volunteer:'.self::VOLUNTEER, 'shift:'.self::SHIFT, 'assignment:'.self::ASSIGNMENT, 'invitation:'.self::INVITATION], \array_slice($this->journal->entries, 3));
        self::assertSame('WITHDRAWN', $this->roster->invitations[self::INVITATION]->status);
        self::assertSame(['CONFIRMED', 2], [$this->roster->assignments[self::ASSIGNMENT]->status, $this->roster->assignments[self::ASSIGNMENT]->version]);
        self::assertSame(['changed:assignment:'.self::ASSIGNMENT.':2'], $this->notices->notices);
        self::assertSame(['assignment.changed:'.self::ASSIGNMENT], $this->audit->rows);

        $this->expectExceptionMessage('This record changed. Refresh before saving.');
        $handler->handle(new ChangeVolunteerAssignmentCommand(self::ACTOR, self::ASSIGNMENT, 1, 'CANCELLED', 'Stale'));
    }
}
