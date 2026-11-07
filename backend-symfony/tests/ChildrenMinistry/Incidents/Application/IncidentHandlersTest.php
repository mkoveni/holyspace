<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Incidents\Application;

use App\ChildrenMinistry\Incidents\Application\Command\AccessIncident\AccessIncidentCommand;
use App\ChildrenMinistry\Incidents\Application\Command\AccessIncident\AccessIncidentHandler;
use App\ChildrenMinistry\Incidents\Application\Command\FollowUpIncident\FollowUpIncidentCommand;
use App\ChildrenMinistry\Incidents\Application\Command\FollowUpIncident\FollowUpIncidentHandler;
use App\ChildrenMinistry\Incidents\Application\Command\PurgeExpiredIncidents\PurgeExpiredIncidentsCommand;
use App\ChildrenMinistry\Incidents\Application\Command\PurgeExpiredIncidents\PurgeExpiredIncidentsHandler;
use App\ChildrenMinistry\Incidents\Application\Command\ReportIncident\ReportIncidentCommand;
use App\ChildrenMinistry\Incidents\Application\Command\ReportIncident\ReportIncidentHandler;
use App\ChildrenMinistry\Incidents\Application\IncidentAccess;
use App\ChildrenMinistry\Incidents\Application\IncidentVisit;
use App\ChildrenMinistry\Incidents\Domain\IncidentStatus;
use App\ChildrenMinistry\Safeguarding\Application\GuardianAuthorityView;
use App\ChildrenMinistry\Safeguarding\Domain\GuardianAuthority;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Domain\IdentityException;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Domain\CalendarDate;
use App\Tests\ChildrenMinistry\Participation\Application\RecordingChildrenAudit;
use App\Tests\ChildrenMinistry\Sessions\Application\FakeSafeguardingReader;
use App\Tests\ChildrenMinistry\Sessions\Application\SessionFixture;
use App\Tests\ChildrenMinistry\Shared\CallLog;
use App\Tests\ChildrenMinistry\Shared\RecordingPeople;
use App\Tests\People\Application\SequentialIds;
use App\Tests\Support\AllowedPermissions;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class IncidentHandlersTest extends TestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string OWNER = '88888888-8888-4888-8888-888888888888';
    private const string REQUEST = '77777777-7777-4777-8777-777777777777';
    private const string CHILD = '20000000-0000-4000-8000-000000000001';
    private const string GUARDIAN = '20000000-0000-4000-8000-000000000002';
    private const string SEGMENT = '30000000-0000-4000-8000-000000000001';
    private const string AUTHORITY = '40000000-0000-4000-8000-000000000001';

    private CallLog $log;
    private MockClock $clock;
    private IncidentWorld $world;
    private SessionFixture $sessions;

    protected function setUp(): void
    {
        $this->log = new CallLog();
        $this->clock = new MockClock('2026-06-07 10:00:00 UTC');
        $this->world = new IncidentWorld($this->log);
        $this->sessions = new SessionFixture($this->log);
        $this->world->visits = [new IncidentVisit(self::SEGMENT, self::CHILD, new DateTimeImmutable('2026-06-07T09:00:00Z'), null)];
    }

    private function calendar(): ChurchCalendar
    {
        return new ChurchCalendar($this->clock, 'Africa/Johannesburg');
    }

    /** @param list<string> $permissions */
    private function report(array $permissions = ['children.incidents.record'], bool $present = true): ReportIncidentHandler
    {
        return new ReportIncidentHandler(
            new AllowedPermissions($permissions),
            new IncidentScopeFake($this->log, $present ? [SessionFixture::SESSION] : []),
            $this->world,
            $this->world,
            $this->sessions,
            new RecordingPeople($this->log, [self::CHILD => true]),
            new RecordingChildrenAudit($this->log),
            $this->clock,
            $this->calendar(),
            new SequentialIds(),
        );
    }

    private function command(?string $description = 'Private fall', string $category = 'INJURY', string $occurred = '2026-06-07T09:30:00Z', string $review = '2026-06-08', string $request = self::REQUEST): ReportIncidentCommand
    {
        return new ReportIncidentCommand(self::ACTOR, $request, self::CHILD, SessionFixture::SESSION, self::SEGMENT, $category, 'LOW', new DateTimeImmutable($occurred), $description, 'Private response', CalendarDate::parse($review), CalendarDate::parse('2027-06-07'));
    }

    public function testReportingLocksTheChildThenTheSessionThenChecksScopeBeforeWriting(): void
    {
        $id = $this->report()->handle($this->command());

        self::assertSame(['person:'.self::CHILD, 'forUpdate:session', 'scope', 'insert:incident', 'append:REPORTED', 'audit:incident.reported'], $this->log->entries);
        $incident = $this->world->requireIncident($id);
        self::assertSame([IncidentStatus::Open, 0, self::ACTOR], [$incident->status, $incident->version, $incident->reportedBy]);
        self::assertSame('Incident recorded.', $this->world->events[0]->note);
        self::assertSame(0, $this->world->events[0]->revision);
    }

    public function testManagersSkipTheSessionScopeAndStaffWithoutAPlaceAreRefused(): void
    {
        $this->report(['children.incidents.manage'], present: false)->handle($this->command());
        self::assertNotContains('scope', $this->log->entries);

        $this->log->entries = [];
        $this->expectException(ChildrenException::class);
        try {
            $this->report(['children.incidents.record'], present: false)->handle($this->command(request: '66666666-6666-4666-8666-666666666666'));
        } finally {
            self::assertNotContains('insert:incident', $this->log->entries);
        }
    }

    public function testNeitherPermissionIsRefusedBeforeAnyLock(): void
    {
        try {
            $this->report(['children.read'])->handle($this->command());
            self::fail('Expected a denial.');
        } catch (IdentityException) {
            self::assertSame([], $this->log->entries);
        }
    }

    public function testARetryReturnsTheSameIncidentAndAChangedPayloadIsAConflict(): void
    {
        $id = $this->report()->handle($this->command());
        $writes = count($this->log->entries);

        self::assertSame($id, $this->report()->handle($this->command()));
        self::assertCount(1, $this->world->incidents);
        self::assertNotContains('insert:incident', array_slice($this->log->entries, $writes));
        $this->expectExceptionMessage('already used for different details');
        $this->report()->handle($this->command('Changed payload'));
    }

    public function testTheIncidentMustFallInsideTheChildsRecordedAttendanceAndDatesMustBeCurrent(): void
    {
        foreach ([
            ['2026-06-07T08:59:00Z', '2026-06-08'],
            ['2026-06-07T11:00:00Z', '2026-06-08'],
            ['2026-06-07T09:30:00Z', '2026-06-06'],
        ] as [$occurred, $review]) {
            try {
                $this->report()->handle($this->command(occurred: $occurred, review: $review));
                self::fail('Expected a rejection.');
            } catch (ChildrenException $invalid) {
                self::assertNotSame('', $invalid->getMessage());
            }
        }
        self::assertSame([], $this->world->incidents);
        $this->world->visits = [new IncidentVisit(self::SEGMENT, self::CHILD, new DateTimeImmutable('2026-06-07T09:00:00Z'), new DateTimeImmutable('2026-06-07T09:30:00Z'))];
        $this->expectExceptionMessage('recorded attendance');
        $this->report()->handle($this->command(occurred: '2026-06-07T09:30:00Z'));
    }

    public function testDetailsAndCategoryAreValidatedWithoutLeakingTheText(): void
    {
        foreach ([$this->command(''), $this->command(null), $this->command(category: 'WEATHER')] as $command) {
            try {
                $this->report()->handle($command);
                self::fail('Expected a rejection.');
            } catch (ChildrenException $invalid) {
                self::assertStringNotContainsString('Private', $invalid->getMessage());
            }
        }
        self::assertSame([], $this->world->incidents);
    }

    /** @param list<string> $permissions */
    private function access(array $permissions = ['children.incidents.manage']): AccessIncidentHandler
    {
        return new AccessIncidentHandler(new AllowedPermissions($permissions), $this->world, new IncidentAccess($this->world), new RecordingChildrenAudit($this->log), $this->clock, $this->calendar());
    }

    public function testEveryAccessIsAuditedAfterTheReadAndRefusalsAreNot(): void
    {
        $id = $this->report()->handle($this->command());
        $this->log->entries = [];

        $view = $this->access()->handle(new AccessIncidentCommand(self::ACTOR, $id, 25, 0));
        $this->access()->handle(new AccessIncidentCommand(self::ACTOR, $id, 1, 1));

        self::assertSame(['read:details', 'read:events', 'audit:incident.accessed', 'read:details', 'read:events', 'audit:incident.accessed'], $this->log->entries);
        self::assertSame(['Private fall', 1], [$view->details->description, $view->totalEvents]);
        $this->log->entries = [];
        try {
            $this->access(['children.incidents.record'])->handle(new AccessIncidentCommand(self::ACTOR, $id, 25, 0));
            self::fail('Expected a denial.');
        } catch (IdentityException) {
            self::assertSame([], $this->log->entries, 'a refusal reads and audits nothing');
        }
        $this->expectException(ChildrenException::class);
        $this->access()->handle(new AccessIncidentCommand(self::ACTOR, $id, 0, 0));
    }

    public function testAnExpiredIncidentCannotBeAccessedAndPurgedDetailsAreGone(): void
    {
        $id = $this->report()->handle($this->command());
        $this->clock->modify('2027-06-08 10:00:00 UTC');

        try {
            $this->access()->handle(new AccessIncidentCommand(self::ACTOR, $id, 25, 0));
            self::fail('Expected not found.');
        } catch (ChildrenException $missing) {
            self::assertSame('Current incident was not found.', $missing->getMessage());
        }
        self::assertArrayHasKey($id, $this->world->details, 'reading never purges');
        $purge = new PurgeExpiredIncidentsHandler(new AllowedPermissions(['children.incidents.manage']), $this->world, new RecordingChildrenAudit($this->log), $this->clock, $this->calendar());
        self::assertSame(1, $purge->handle(new PurgeExpiredIncidentsCommand(self::ACTOR, 100)));
        self::assertArrayNotHasKey($id, $this->world->details);
        self::assertContains('audit:incident.details.purged', $this->log->entries);
        self::assertSame(0, $purge->handle(new PurgeExpiredIncidentsCommand(self::ACTOR, 100)));
    }

    public function testPurgeIsBoundedAndNeedsManage(): void
    {
        $purge = new PurgeExpiredIncidentsHandler(new AllowedPermissions(['children.incidents.manage']), $this->world, new RecordingChildrenAudit($this->log), $this->clock, $this->calendar());
        foreach ([0, 101] as $limit) {
            try {
                $purge->handle(new PurgeExpiredIncidentsCommand(self::ACTOR, $limit));
                self::fail('Expected a rejection.');
            } catch (ChildrenException) {
                self::assertSame([], $this->log->entries);
            }
        }
        $this->expectException(IdentityException::class);
        new PurgeExpiredIncidentsHandler(new AllowedPermissions(['children.incidents.record']), $this->world, new RecordingChildrenAudit($this->log), $this->clock, $this->calendar())->handle(new PurgeExpiredIncidentsCommand(self::ACTOR, 5));
    }

    private function followUp(): FollowUpIncidentHandler
    {
        $safeguarding = new FakeSafeguardingReader();
        $safeguarding->authorities[self::AUTHORITY] = new GuardianAuthorityView(self::AUTHORITY, self::CHILD, self::GUARDIAN, new DateTimeImmutable('2026-01-01T00:00:00Z'), new DateTimeImmutable('2027-01-01T00:00:00Z'), 'private', 'v', null, null, 0);
        $locks = new FakeGuardianLocks($this->log, [self::AUTHORITY => GuardianAuthority::reconstitute(self::AUTHORITY, self::CHILD, self::GUARDIAN, new DateTimeImmutable('2026-01-01T00:00:00Z'), new DateTimeImmutable('2027-01-01T00:00:00Z'), 'private', 'v', null, null, 0)]);

        return new FollowUpIncidentHandler(
            new AllowedPermissions(['children.incidents.manage']),
            $this->world,
            $safeguarding,
            $locks,
            new FakeOwners([self::OWNER]),
            new RecordingPeople($this->log, [self::GUARDIAN => true], [self::GUARDIAN => '1985-01-01']),
            new RecordingChildrenAudit($this->log),
            $this->clock,
            $this->calendar(),
            new SequentialIds(),
        );
    }

    private function step(string $id, int $version, string $action, ?string $note = 'Private note', ?string $owner = null, ?string $authority = null): void
    {
        $this->followUp()->handle(new FollowUpIncidentCommand(self::ACTOR, $id, $version, $action, $owner, null === $owner ? null : CalendarDate::parse('2026-06-09'), $authority, $note));
    }

    public function testFollowUpLocksTheIncidentThenTheAuthorityAndWritesHistoryWithTheNewVersion(): void
    {
        $id = $this->report()->handle($this->command());
        $this->step($id, 0, 'TRIAGED', owner: self::OWNER);
        $this->log->entries = [];

        $this->step($id, 1, 'GUARDIAN_CONTACT', 'Spoke to the parent', authority: self::AUTHORITY);

        self::assertSame(['lock:incident', 'lock:authority', 'update:incident', 'append:GUARDIAN_CONTACT', 'audit:incident.followup.guardian_contact'], array_values(array_filter($this->log->entries, static fn (string $e): bool => !str_starts_with($e, 'person:'))));
        $last = end($this->world->events);
        self::assertNotFalse($last);
        self::assertSame([2, self::GUARDIAN, 'Spoke to the parent'], [$last->revision, $last->guardianId, $last->note]);
    }

    public function testTriageNeedsAnEligibleOwnerAndAFutureReviewDate(): void
    {
        $id = $this->report()->handle($this->command());
        foreach ([['88888888-8888-4888-8888-888888888889', '2026-06-09'], [null, '2026-06-09']] as [$owner, $review]) {
            try {
                $this->followUp()->handle(new FollowUpIncidentCommand(self::ACTOR, $id, 0, 'TRIAGED', $owner, CalendarDate::parse($review), null, 'x'));
                self::fail('Expected a rejection.');
            } catch (ChildrenException $invalid) {
                self::assertSame('Choose an active account with incident-management access.', $invalid->getMessage());
            }
        }
        self::assertCount(1, $this->world->events);
        $this->expectException(ChildrenException::class);
        $this->followUp()->handle(new FollowUpIncidentCommand(self::ACTOR, $id, 0, 'TRIAGED', self::OWNER, CalendarDate::parse('2026-06-01'), null, 'x'));
    }

    public function testRefusedFollowUpsWriteNothing(): void
    {
        $id = $this->report()->handle($this->command());
        $events = count($this->world->events);
        foreach ([
            [0, 'GUARDIAN_CONTACT', null, 'Choose current verified guardian authority.'],
            [0, 'GUARDIAN_CONTACT', '40000000-0000-4000-8000-0000000000ff', 'Choose current verified guardian authority.'],
            [0, 'CLOSED', null, 'Assign responsibility before closing this incident.'],
            [7, 'ACTION', null, 'This record changed. Reload before trying again.'],
            [0, 'REOPENED', null, 'Only closed incidents can be reopened.'],
            [0, 'REPORTED', null, 'Choose an incident follow-up action.'],
            [0, 'DELETE', null, 'Choose an incident follow-up action.'],
        ] as [$version, $action, $authority, $message]) {
            try {
                $this->step($id, $version, $action, authority: $authority);
                self::fail("Expected $action to be refused.");
            } catch (ChildrenException $refused) {
                self::assertSame($message, $refused->getMessage(), $action);
            }
        }
        self::assertCount($events, $this->world->events);
        self::assertSame(0, $this->world->requireIncident($id)->version);
        try {
            $this->step($id, 0, 'ACTION', note: ' ');
            self::fail('A note is required.');
        } catch (ChildrenException) {
            self::assertCount($events, $this->world->events);
        }
    }

    public function testAnExpiredIncidentCannotBeFollowedUp(): void
    {
        $id = $this->report()->handle($this->command());
        $this->clock->modify('2027-06-08 10:00:00 UTC');

        $this->expectExceptionMessage('Current incident was not found.');
        $this->step($id, 0, 'ACTION');
    }
}
