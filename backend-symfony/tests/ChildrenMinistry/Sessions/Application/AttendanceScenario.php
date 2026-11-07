<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Participation\Application\AssignmentView;
use App\ChildrenMinistry\Participation\Application\EnrolmentView;
use App\ChildrenMinistry\Safeguarding\Application\ConsentView;
use App\ChildrenMinistry\Safeguarding\Application\GuardianAuthorityView;
use App\ChildrenMinistry\Safeguarding\Application\PickupAuthorizationView;
use App\ChildrenMinistry\Sessions\Application\AdmissionPolicy;
use App\ChildrenMinistry\Sessions\Application\Command\CheckInChild\CheckInChildHandler;
use App\ChildrenMinistry\Sessions\Application\Handover;
use App\ChildrenMinistry\Sessions\Application\StaffScope;
use App\ChildrenMinistry\Sessions\Domain\Attendance;
use App\ChildrenMinistry\Sessions\Domain\SessionStatus;
use App\ChildrenMinistry\Sessions\Domain\StaffRole;
use App\ChildrenMinistry\Sessions\Infrastructure\SecurePickupTokens;
use App\ChildrenMinistry\Shared\Application\ParticipantAccess;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Domain\CalendarDate;
use App\Tests\ChildrenMinistry\Participation\Application\RecordingChildrenAudit;
use App\Tests\ChildrenMinistry\Shared\CallLog;
use App\Tests\ChildrenMinistry\Shared\RecordingChildLocks;
use App\Tests\ChildrenMinistry\Shared\RecordingPeople;
use App\Tests\People\Application\SequentialIds;
use App\Tests\Support\AllowedPermissions;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * An open session at 11:00-13:00 Johannesburg time with one cleared leader, and a child who is enrolled, placed,
 * consented and collectable by one adult. Tests break one thing at a time. The clock stands 15 minutes before start.
 */
abstract class AttendanceScenario extends TestCase
{
    protected const string ACTOR = '99999999-9999-4999-8999-999999999999';
    protected const string LEADER = '10000000-0000-4000-8000-000000000001';
    /** The leader's account: the station user who is present on the session. */
    protected const string STATION = 'acct-'.self::LEADER;
    protected const string CHILD = '30000000-0000-4000-8000-000000000001';
    protected const string COLLECTOR = '30000000-0000-4000-8000-000000000002';
    protected const string AUTHORITY = '40000000-0000-4000-8000-000000000001';
    protected const string REQUEST = '50000000-0000-4000-8000-000000000001';
    protected const string GRANT = '60000000-0000-4000-8000-000000000001';

    protected CallLog $log;
    protected SessionFixture $world;
    protected FakeSafeguardingReader $safeguarding;
    protected MockClock $clock;

    protected function setUp(): void
    {
        $this->log = new CallLog();
        $this->clock = new MockClock('2026-06-07 08:45:00 UTC');
        $this->world = new SessionFixture($this->log, SessionStatus::Open);
        $this->world->serve(self::LEADER, StaffRole::Leader);
        $this->safeguarding = new FakeSafeguardingReader();
        $this->admit(self::CHILD);
        $this->safeguarding->grants[] = new PickupAuthorizationView(self::GRANT, SessionFixture::PROGRAMME, self::CHILD, self::COLLECTOR, self::AUTHORITY, $this->at('2026-01-01T00:00:00Z'), $this->at('2027-01-01T00:00:00Z'), 'ID', self::ACTOR, null, null, 0);
        $this->log->entries = [];
    }

    protected function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time);
    }

    /** Gives the child an enrolment, a class placement, a guardian authority and a consent that all cover the session. */
    protected function admit(string $child): void
    {
        $from = CalendarDate::parse('2026-01-01');
        $until = CalendarDate::parse('2027-01-01');
        $this->world->enrolments[SessionFixture::PROGRAMME.$child] = new EnrolmentView('e1', SessionFixture::PROGRAMME, $child, $from, $until, null, 0, $this->at('2026-01-01T00:00:00Z'));
        $this->world->placements[SessionFixture::CLASS_ID.$child] = new AssignmentView('a1', SessionFixture::PROGRAMME, SessionFixture::CLASS_ID, $child, 'e1', $from, $until, null, 0);
        $this->safeguarding->authorities[self::AUTHORITY] = new GuardianAuthorityView(self::AUTHORITY, $child, 'guardian', $this->at('2026-01-01T00:00:00Z'), $this->at('2027-01-01T00:00:00Z'), 'ID', self::ACTOR, null, null, 0);
        $this->safeguarding->consents[] = new ConsentView('c1', SessionFixture::PROGRAMME, (string) $this->world->policyId, $child, self::AUTHORITY, $this->at('2026-01-01T00:00:00Z'), $this->at('2027-01-01T00:00:00Z'), $this->at('2026-01-01T00:00:00Z'), self::ACTOR, 'Signed', null, null, 0);
    }

    protected function people(string $childBirth = '2018-01-01', string $collectorBirth = '1985-01-01'): RecordingPeople
    {
        return new RecordingPeople($this->log, [self::CHILD => true, self::COLLECTOR => true, '30000000-0000-4000-8000-000000000003' => true], [self::CHILD => $childBirth, self::COLLECTOR => $collectorBirth, '30000000-0000-4000-8000-000000000003' => '2018-02-01']);
    }

    protected function access(string $childBirth = '2018-01-01', string $collectorBirth = '1985-01-01'): ParticipantAccess
    {
        return new ParticipantAccess($this->people($childBirth, $collectorBirth), new RecordingChildLocks($this->log));
    }

    /** @param list<string> $permissions */
    protected function permissions(array $permissions): AllowedPermissions
    {
        return new AllowedPermissions($permissions);
    }

    /** @param list<string> $permissions */
    protected function scope(array $permissions): StaffScope
    {
        return new StaffScope($this->permissions($permissions), $this->world);
    }

    /** @param list<string> $permissions */
    protected function checkIn(array $permissions = ['children.station'], string $childBirth = '2018-01-01'): CheckInChildHandler
    {
        return new CheckInChildHandler(
            $this->permissions($permissions),
            new RecordingChildrenAudit($this->log),
            $this->clock,
            new SequentialIds(),
            $this->access($childBirth),
            $this->scope($permissions),
            $this->world,
            $this->world,
            $this->world,
            $this->world->operations(),
            $this->admission($childBirth),
        );
    }

    protected function admission(string $childBirth = '2018-01-01'): AdmissionPolicy
    {
        return new AdmissionPolicy($this->world, $this->safeguarding, $this->access($childBirth), $this->world->operations());
    }

    /** @param list<string> $permissions */
    protected function handover(array $permissions = ['children.station'], string $collectorBirth = '1985-01-01'): Handover
    {
        return new Handover(
            $this->permissions($permissions),
            $this->access('2018-01-01', $collectorBirth),
            $this->scope($permissions),
            $this->world,
            $this->world,
            $this->world,
            $this->world,
            $this->safeguarding,
            new SecurePickupTokens(),
            new RecordingChildrenAudit($this->log),
            $this->clock,
            new ChurchCalendar($this->clock, 'Africa/Johannesburg'),
            new SequentialIds(),
        );
    }

    /** A child already checked in, optionally with a label for the given code. */
    protected function present(?string $code = null, string $id = 'b0000000-0000-4000-8000-000000000001'): Attendance
    {
        $visit = Attendance::arrive($id, self::CHILD, SessionFixture::SESSION, $this->at('2026-06-07T08:40:00Z'), self::STATION, self::REQUEST);
        if (null !== $code) {
            $tokens = new SecurePickupTokens();
            $visit = $visit->issueLabel($tokens->hash($code), $this->at('2026-06-08T08:40:00Z'), 0, null);
        }
        $this->world->attendances[$id] = $visit;
        $this->log->entries = [];

        return $visit;
    }

    protected function refused(string $message, callable $action): void
    {
        try {
            $action();
            self::fail('Expected a rejection.');
        } catch (ChildrenException $error) {
            self::assertSame($message, $error->getMessage());
        }
    }
}
