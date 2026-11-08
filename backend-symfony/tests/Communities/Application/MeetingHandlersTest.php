<?php

declare(strict_types=1);

namespace App\Tests\Communities\Application;

use App\Communities\Application\Command\AddGroupGuest\AddGroupGuestCommand;
use App\Communities\Application\Command\AddGroupGuest\AddGroupGuestHandler;
use App\Communities\Application\Command\FinishGroupMeeting\FinishGroupMeetingCommand;
use App\Communities\Application\Command\FinishGroupMeeting\FinishGroupMeetingHandler;
use App\Communities\Application\Command\PrepareGroupRoster\PrepareGroupRosterCommand;
use App\Communities\Application\Command\PrepareGroupRoster\PrepareGroupRosterHandler;
use App\Communities\Application\Command\RecordGroupAttendance\RecordGroupAttendanceCommand;
use App\Communities\Application\Command\RecordGroupAttendance\RecordGroupAttendanceHandler;
use App\Communities\Application\CommunityAccess;
use App\Communities\Application\MeetingCalendar;
use App\Communities\Application\MeetingLocks;
use App\Communities\Domain\Community;
use App\Communities\Domain\CommunityException;
use App\Communities\Domain\GroupMeeting;
use App\Communities\Domain\Membership;
use App\Communities\Domain\RosterEntry;
use App\Events\Application\Contract\CalendarOccurrence;
use App\IdentityAccess\Domain\IdentityException;
use App\Tests\Events\Application\LockLog;
use App\Tests\Events\Application\RecordingPeople;
use App\Tests\People\Application\SequentialIds;
use App\Tests\Support\AllowedPermissions;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Meeting commands in isolation: permission first, the documented lock order (person, occurrence, community, meeting),
 * stale versions refused, and history written only on success.
 */
final class MeetingHandlersTest extends TestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string COMMUNITY = 'c0000000-0000-4000-8000-000000000001';
    private const string MEETING = 'm0000000-0000-4000-8000-000000000001';
    private const string OCCURRENCE = 'o0000000-0000-4000-8000-000000000001';
    private const string PERSON = 'p0000000-0000-4000-8000-000000000001';

    private LockLog $log;
    private RecordingPeople $people;
    private LockingMeetings $meetings;
    private LockingCommunities $communities;
    private LockingCalendar $calendar;
    private RecordingAudit $audit;
    private MockClock $clock;
    private AllowedPermissions $permissions;

    protected function setUp(): void
    {
        $this->log = new LockLog();
        $this->people = new RecordingPeople($this->log);
        $this->people->add(self::PERSON);
        $this->meetings = new LockingMeetings($this->log);
        $this->communities = new LockingCommunities($this->log);
        $this->calendar = new LockingCalendar($this->log);
        $this->audit = new RecordingAudit();
        $this->clock = new MockClock('2026-07-05 09:30:00 UTC');
        $this->permissions = new AllowedPermissions(['ministries.manage']);

        $start = new DateTimeImmutable('2026-07-05 10:00:00 UTC');
        $this->communities->insertCommunity(Community::create(self::COMMUNITY, Community::GROUP, 'Home', null, null, null));
        $this->communities->insertMembership(Membership::start('ms', self::COMMUNITY, self::PERSON, $start->modify('-1 day')));
        $this->calendar->dates[self::OCCURRENCE] = new CalendarOccurrence(self::OCCURRENCE, $start, $start->modify('+1 hour'), 'UTC', 'Lounge', 'SCHEDULED', 0);
        $this->meetings->insertMeeting(GroupMeeting::schedule(self::MEETING, self::COMMUNITY, self::OCCURRENCE, $start));
    }

    private function access(): CommunityAccess
    {
        return new CommunityAccess($this->permissions, new SignedInActor(self::ACTOR), new ActiveCommunities(), $this->clock);
    }

    private function locks(): MeetingLocks
    {
        return new MeetingLocks($this->meetings, $this->communities, new MeetingCalendar($this->calendar), $this->access());
    }

    private function prepare(int $version = 0): void
    {
        (new PrepareGroupRosterHandler($this->access(), $this->locks(), $this->communities, $this->meetings, $this->audit, new SequentialIds(), $this->clock))
            ->handle(new PrepareGroupRosterCommand(self::ACTOR, self::MEETING, $version));
    }

    public function testPreparingLocksOccurrenceThenCommunityThenMeetingAndListsCurrentMembers(): void
    {
        $this->prepare();

        self::assertSame(['occurrence:'.self::OCCURRENCE, 'community:'.self::COMMUNITY, 'meeting:'.self::MEETING], $this->log->locks);
        self::assertCount(1, $this->meetings->roster);
        self::assertSame([true, 1], [$this->meetings->meetings[self::MEETING]->rosterFrozen, $this->meetings->meetings[self::MEETING]->version]);
        self::assertSame(['ROSTER_PREPARED'], $this->audit->actions);
    }

    public function testAddingAGuestLocksThePersonFirst(): void
    {
        $this->prepare();
        $this->log->locks = [];
        $guest = 'p0000000-0000-4000-8000-000000000002';
        $this->people->add($guest);

        (new AddGroupGuestHandler($this->access(), $this->people, $this->locks(), $this->meetings, $this->audit, new SequentialIds(), $this->clock))
            ->handle(new AddGroupGuestCommand(self::ACTOR, self::MEETING, 1, $guest, 'Welcome'));

        self::assertSame(['person:'.$guest, 'occurrence:'.self::OCCURRENCE, 'community:'.self::COMMUNITY, 'meeting:'.self::MEETING], $this->log->locks);
        self::assertSame(2, $this->meetings->meetings[self::MEETING]->version);
        self::assertSame(['ROSTER_PREPARED', 'GUEST_ADDED'], $this->audit->actions);
    }

    public function testRecordingAttendanceLocksThePersonFirstAndAStaleVersionWritesNothing(): void
    {
        $this->prepare();
        $this->log->locks = [];
        $row = (string) array_key_first($this->meetings->roster);
        $handler = new RecordGroupAttendanceHandler($this->access(), $this->people, $this->locks(), $this->meetings, $this->audit, $this->clock);

        $handler->handle(new RecordGroupAttendanceCommand(self::ACTOR, $row, 0, 'PRESENT', 'Seen'));

        self::assertSame(['person:'.self::PERSON, 'occurrence:'.self::OCCURRENCE, 'community:'.self::COMMUNITY, 'meeting:'.self::MEETING], $this->log->locks);
        try {
            $handler->handle(new RecordGroupAttendanceCommand(self::ACTOR, $row, 0, 'ABSENT', 'Stale'));
            self::fail('a stale version was accepted');
        } catch (CommunityException $e) {
            self::assertSame('These details changed. Refresh and review before saving.', $e->getMessage());
        }
        self::assertSame(['PRESENT', 1], [$this->meetings->roster[$row]->status, $this->meetings->roster[$row]->version]);
        self::assertSame(['ROSTER_PREPARED', 'ATTENDANCE_PRESENT'], $this->audit->actions);
    }

    public function testWithoutPermissionNothingIsLockedOrWritten(): void
    {
        $this->permissions = new AllowedPermissions([]);
        $this->meetings->roster['r'] = RosterEntry::guest('r', self::MEETING, self::PERSON);
        $commands = [
            fn () => $this->prepare(),
            fn () => (new AddGroupGuestHandler($this->access(), $this->people, $this->locks(), $this->meetings, $this->audit, new SequentialIds(), $this->clock))->handle(new AddGroupGuestCommand(self::ACTOR, self::MEETING, 0, self::PERSON, 'x')),
            fn () => (new RecordGroupAttendanceHandler($this->access(), $this->people, $this->locks(), $this->meetings, $this->audit, $this->clock))->handle(new RecordGroupAttendanceCommand(self::ACTOR, 'r', 0, 'PRESENT', 'x')),
            fn () => (new FinishGroupMeetingHandler($this->access(), $this->locks(), $this->meetings, $this->audit, $this->clock))->handle(new FinishGroupMeetingCommand(self::ACTOR, self::MEETING, 0, 'CANCELLED', 'x')),
        ];
        foreach ($commands as $command) {
            try {
                $command();
                self::fail('the command ran without permission');
            } catch (IdentityException) {
            }
        }

        self::assertSame([], $this->log->locks);
        self::assertSame([], $this->audit->actions);
    }

    public function testLeadersMayCloseButNotCancel(): void
    {
        $this->permissions = new AllowedPermissions(['ministries.lead']);
        $this->prepare();
        $finish = new FinishGroupMeetingHandler($this->access(), $this->locks(), $this->meetings, $this->audit, $this->clock);

        try {
            $finish->handle(new FinishGroupMeetingCommand(self::ACTOR, self::MEETING, 1, 'CANCELLED', 'Rain'));
            self::fail('a leader cancelled the meeting');
        } catch (IdentityException) {
        }
        $this->clock->modify('2026-07-05 11:00:00 UTC');
        $finish->handle(new FinishGroupMeetingCommand(self::ACTOR, self::MEETING, 1, 'CLOSED', 'Reviewed'));

        self::assertSame(GroupMeeting::CLOSED, $this->meetings->meetings[self::MEETING]->status);
        self::assertSame(['ROSTER_PREPARED', 'MEETING_CLOSED'], $this->audit->actions);
    }
}
