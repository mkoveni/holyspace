<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Sessions\Application\Command\CreateSession\CreateSessionCommand;
use App\ChildrenMinistry\Sessions\Application\Command\CreateSession\CreateSessionHandler;
use App\ChildrenMinistry\Sessions\Application\Command\SetSessionStatus\SetSessionStatusCommand;
use App\ChildrenMinistry\Sessions\Application\Command\SetSessionStatus\SetSessionStatusHandler;
use App\ChildrenMinistry\Sessions\Domain\SessionStatus;
use App\ChildrenMinistry\Sessions\Domain\StaffRole;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Domain\IdentityException;
use App\Shared\Application\ChurchCalendar;
use App\Tests\ChildrenMinistry\Participation\Application\RecordingChildrenAudit;
use App\Tests\ChildrenMinistry\Shared\CallLog;
use App\Tests\People\Application\SequentialIds;
use App\Tests\Support\AllowedPermissions;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/** Order is the safety property: permission first, then the session row lock, then the rules, then the write and audit. */
final class SessionHandlersTest extends TestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string LEADER = '10000000-0000-4000-8000-000000000001';
    private const string HELPER = '10000000-0000-4000-8000-000000000002';

    private CallLog $log;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->log = new CallLog();
        $this->clock = new MockClock('2026-06-07 08:30:00 UTC');
    }

    /** @param list<string> $permissions */
    private function create(SessionFixture $world, array $permissions = ['children.manage']): CreateSessionHandler
    {
        return new CreateSessionHandler(new AllowedPermissions($permissions), new RecordingChildrenAudit($this->log), $this->clock, new SequentialIds(), $world, $world, $world);
    }

    /** @param list<string> $permissions */
    private function statusHandler(SessionFixture $world, array $permissions = ['children.manage', 'children.incidents.manage']): SetSessionStatusHandler
    {
        return new SetSessionStatusHandler(new AllowedPermissions($permissions), new RecordingChildrenAudit($this->log), $this->clock, $world, $world->operations(), $world, $world, new ChurchCalendar($this->clock, 'Africa/Johannesburg'));
    }

    private function command(SessionStatus $status, int $version = 0, bool $acknowledge = false, ?string $reason = 'Because'): SetSessionStatusCommand
    {
        return new SetSessionStatusCommand(self::ACTOR, SessionFixture::SESSION, $version, $status, $reason, $acknowledge);
    }

    private function createCommand(int $seats = 10, string $room = SessionFixture::ROOM, string $class = SessionFixture::CLASS_ID): CreateSessionCommand
    {
        return new CreateSessionCommand(self::ACTOR, $class, $room, new DateTimeImmutable('2026-06-14T09:00:00Z'), new DateTimeImmutable('2026-06-14T11:00:00Z'), $seats);
    }

    private function assertRefused(string $message, callable $action): void
    {
        try {
            $action();
            self::fail('Expected a rejection.');
        } catch (ChildrenException $error) {
            self::assertSame($message, $error->getMessage());
        }
    }

    public function testCreatingPlansASessionAndAuditsIt(): void
    {
        $world = new SessionFixture($this->log);

        $id = $this->create($world)->handle($this->createCommand());

        self::assertSame(['insert:session', 'audit:session.created'], $this->log->entries);
        self::assertSame(SessionStatus::Planned, $world->sessions[$id]->status);
        self::assertSame(0, $world->sessions[$id]->version);
        self::assertSame(10, $world->sessions[$id]->seatLimit);
    }

    public function testCreatingNeedsPermissionAndTouchesNothingWithout(): void
    {
        $world = new SessionFixture($this->log);

        $this->expectException(IdentityException::class);
        try {
            $this->create($world, ['children.read'])->handle($this->createCommand());
        } finally {
            self::assertSame([], $this->log->entries);
            self::assertCount(1, $world->sessions);
        }
    }

    public function testCreatingChecksTheClassTheRoomAndTheRoomCapacity(): void
    {
        $world = new SessionFixture($this->log);

        $this->assertRefused('MinistryClass was not found.', fn () => $this->create($world)->handle($this->createCommand(class: 'c0000000-0000-4000-8000-0000000000ff')));
        $this->assertRefused('Room was not found.', fn () => $this->create($world)->handle($this->createCommand(room: 'd0000000-0000-4000-8000-0000000000ff')));
        $this->assertRefused('Session seats cannot exceed room capacity.', fn () => $this->create($world)->handle($this->createCommand(seats: 13)));
        self::assertSame([], $this->log->entries);
        $this->create($world)->handle($this->createCommand(seats: 12));
        self::assertSame(['insert:session', 'audit:session.created'], $this->log->entries);
    }

    public function testChangingStatusLocksTheSessionFirstThenWritesAndAudits(): void
    {
        $world = new SessionFixture($this->log);

        $this->statusHandler($world)->handle($this->command(SessionStatus::Cancelled));

        self::assertSame(['forUpdate:session', 'read:occupancy', 'update:session:CANCELLED', 'audit:session.status.changed'], $this->log->entries);
        self::assertSame(1, $world->sessions[SessionFixture::SESSION]->version);
    }

    public function testStatusChangeNeedsPermissionBeforeAnyLock(): void
    {
        $world = new SessionFixture($this->log);

        try {
            $this->statusHandler($world, ['children.read'])->handle($this->command(SessionStatus::Cancelled));
            self::fail('Expected a refusal.');
        } catch (IdentityException) {
        }

        self::assertSame([], $this->log->entries);
    }

    public function testAnUnknownSessionIsMissingAfterTheLockAttempt(): void
    {
        $world = new SessionFixture($this->log);

        $this->assertRefused('MinistrySession was not found.', fn () => $this->statusHandler($world)->handle(new SetSessionStatusCommand(self::ACTOR, 'e0000000-0000-4000-8000-0000000000ff', 0, SessionStatus::Open, 'x')));

        self::assertSame(['forUpdate:session'], $this->log->entries);
    }

    public function testAReasonIsRequiredAndNothingIsWrittenWithoutOne(): void
    {
        $world = new SessionFixture($this->log);

        foreach ([null, '', '   ', str_repeat('x', 501)] as $reason) {
            $this->assertRefused('A value is missing or exceeds its maximum length of 500.', fn () => $this->statusHandler($world)->handle($this->command(SessionStatus::Cancelled, reason: $reason)));
        }

        self::assertNotContains('update:session:CANCELLED', $this->log->entries);
        self::assertSame(0, $world->sessions[SessionFixture::SESSION]->version);
    }

    public function testChildrenMustLeaveBeforeClosingOrCancelling(): void
    {
        foreach ([SessionStatus::Closed, SessionStatus::Cancelled] as $target) {
            $world = new SessionFixture($this->log, SessionStatus::Open);
            $world->occupancy = 2;
            $this->log->entries = [];

            $this->assertRefused('Collect or transfer all children before closing or cancelling.', fn () => $this->statusHandler($world)->handle($this->command($target, 0, true)));

            self::assertSame(['forUpdate:session', 'read:occupancy'], $this->log->entries);
            self::assertSame(SessionStatus::Open, $world->sessions[SessionFixture::SESSION]->status);
        }
    }

    public function testOccupancyDoesNotBlockOtherChanges(): void
    {
        $world = new SessionFixture($this->log, SessionStatus::Open);
        $world->occupancy = 4;
        $world->serve(self::LEADER, StaffRole::Leader);

        $this->statusHandler($world)->handle($this->command(SessionStatus::NeedsReview));

        self::assertSame(SessionStatus::NeedsReview, $world->sessions[SessionFixture::SESSION]->status);
    }

    public function testClosingWithOpenIncidentsNeedsTheIncidentPermissionAndAnAcknowledgement(): void
    {
        $world = new SessionFixture($this->log, SessionStatus::Open);
        $world->unresolved = 1;

        try {
            $this->statusHandler($world, ['children.manage'])->handle($this->command(SessionStatus::Closed, 0, true));
            self::fail('Expected a refusal.');
        } catch (IdentityException) {
        }
        self::assertSame(['forUpdate:session', 'read:occupancy', 'read:incidents:2026-06-07'], $this->log->entries);

        $this->log->entries = [];
        $this->assertRefused('Review and acknowledge outstanding incident follow-ups before closing this session.', fn () => $this->statusHandler($world)->handle($this->command(SessionStatus::Closed)));
        self::assertSame(['forUpdate:session', 'read:occupancy', 'read:incidents:2026-06-07'], $this->log->entries);
        self::assertSame(SessionStatus::Open, $world->sessions[SessionFixture::SESSION]->status);
    }

    public function testAcknowledgedClosureAuditsTheAcknowledgementBeforeTheChange(): void
    {
        $world = new SessionFixture($this->log, SessionStatus::Open);
        $world->unresolved = 3;

        $this->statusHandler($world)->handle($this->command(SessionStatus::Closed, 0, true));

        self::assertSame(['forUpdate:session', 'read:occupancy', 'read:incidents:2026-06-07', 'audit:session.incidents.acknowledged', 'update:session:CLOSED', 'audit:session.status.changed'], $this->log->entries);
    }

    public function testTheIncidentDayIsTheChurchsDayNotUtcs(): void
    {
        $world = new SessionFixture($this->log, SessionStatus::Open);
        $world->unresolved = 1;
        $this->clock = new MockClock('2026-06-07 22:30:00 UTC');

        $this->statusHandler($world)->handle($this->command(SessionStatus::Closed, 0, true));

        self::assertContains('read:incidents:2026-06-08', $this->log->entries);
    }

    public function testClosingWithoutOpenIncidentsNeedsNoAcknowledgement(): void
    {
        $world = new SessionFixture($this->log, SessionStatus::Open);

        $this->statusHandler($world, ['children.manage'])->handle($this->command(SessionStatus::Closed));

        self::assertSame(SessionStatus::Closed, $world->sessions[SessionFixture::SESSION]->status);
        self::assertNotContains('audit:session.incidents.acknowledged', $this->log->entries);
    }

    public function testOpeningNeedsEligiblePresentStaffIncludingALeader(): void
    {
        $message = 'Opening requires eligible present staff, including a leader, and sufficient capacity.';
        $world = new SessionFixture($this->log);
        $this->assertRefused($message, fn () => $this->statusHandler($world)->handle($this->command(SessionStatus::Open)));

        $world->serve(self::HELPER, StaffRole::Assistant);
        $this->assertRefused($message, fn () => $this->statusHandler($world)->handle($this->command(SessionStatus::Open)));

        $world->serve(self::LEADER, StaffRole::Leader, present: false);
        $this->assertRefused($message, fn () => $this->statusHandler($world)->handle($this->command(SessionStatus::Open)));
        self::assertSame(SessionStatus::Planned, $world->sessions[SessionFixture::SESSION]->status);
        self::assertNotContains('update:session:OPEN', $this->log->entries);

        $world->staff = array_values(array_filter($world->staff, static fn ($staff): bool => $staff->workerId !== self::LEADER));
        $world->serve(self::LEADER, StaffRole::Leader);
        $this->statusHandler($world)->handle($this->command(SessionStatus::Open));
        self::assertSame(SessionStatus::Open, $world->sessions[SessionFixture::SESSION]->status);
    }

    public function testAStaleVersionWritesAndAuditsNothing(): void
    {
        $world = new SessionFixture($this->log);

        $this->assertRefused('This record changed. Reload before trying again.', fn () => $this->statusHandler($world)->handle($this->command(SessionStatus::Cancelled, 5)));

        self::assertSame(['forUpdate:session', 'read:occupancy'], $this->log->entries);
        self::assertSame(0, $world->sessions[SessionFixture::SESSION]->version);
    }

    public function testATerminalSessionCannotBeReopened(): void
    {
        $world = new SessionFixture($this->log, SessionStatus::Cancelled);
        $world->serve(self::LEADER, StaffRole::Leader);
        $world->serve(self::HELPER, StaffRole::Assistant);

        $this->assertRefused('This session is terminal and cannot be reopened.', fn () => $this->statusHandler($world)->handle($this->command(SessionStatus::Planned)));
        $this->assertRefused('This session is terminal and cannot be reopened.', fn () => $this->statusHandler($world)->handle($this->command(SessionStatus::Open)));
    }
}
