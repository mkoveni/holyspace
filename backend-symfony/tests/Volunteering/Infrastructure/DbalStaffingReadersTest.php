<?php

declare(strict_types=1);

namespace App\Tests\Volunteering\Infrastructure;

use App\Tests\Support\DatabaseTestCase;
use App\Tests\Volunteering\Application\FakeActivitySource;
use App\Tests\Volunteering\Application\FakeDirectory;
use App\Volunteering\Application\ActivityRegistry;
use App\Volunteering\Application\Contract\SourceActivity;
use App\Volunteering\Application\Contract\StaffingUnavailable;
use App\Volunteering\Application\Fingerprint;
use App\Volunteering\Application\PublishedStaffingDirectory;
use App\Volunteering\Application\VolunteerReadiness;
use App\Volunteering\Infrastructure\Persistence\DbalRosterReader;
use App\Volunteering\Infrastructure\Persistence\DbalTeamReader;
use App\Volunteering\Infrastructure\Persistence\DbalVolunteerReader;
use DateTimeImmutable;

/** Proves the V14 adapters and the readiness rules agree on real rows. */
final class DbalStaffingReadersTest extends DatabaseTestCase
{
    private const VOLUNTEER = '10000000-0000-4000-8000-000000000001';
    private const PERSON = '10000000-0000-4000-8000-000000000002';
    private const TEAM = '10000000-0000-4000-8000-000000000003';
    private const ROLE = '10000000-0000-4000-8000-000000000004';
    private const SHIFT = '10000000-0000-4000-8000-000000000005';
    private const ASSIGNMENT = '10000000-0000-4000-8000-000000000006';
    private const SESSION = '10000000-0000-4000-8000-000000000007';
    private const CREDENTIAL_TYPE = '10000000-0000-4000-8000-000000000008';

    private const START = '2026-11-08 08:00:00+00';
    private const END = '2026-11-08 10:00:00+00';

    public function testReadersMapEveryV14TableTheDirectoryNeeds(): void
    {
        $this->seed();
        $volunteers = new DbalVolunteerReader($this->connection);
        $teams = new DbalTeamReader($this->connection);
        $roster = new DbalRosterReader($this->connection);
        $from = new DateTimeImmutable('2026-11-08T08:00:00Z');
        $until = new DateTimeImmutable('2026-11-08T10:00:00Z');

        $volunteer = $volunteers->volunteer(self::VOLUNTEER);
        self::assertSame(self::PERSON, $volunteer?->personId);
        self::assertSame(2, $volunteer->availabilityRevision);
        self::assertNull($volunteers->volunteer(self::TEAM));
        self::assertSame('Police clearance', $volunteers->credentialTypes()[0]->name);
        self::assertTrue($volunteers->credentials(self::VOLUNTEER)[0]->window->covers($from, $until));
        self::assertCount(1, $volunteers->availability(self::VOLUNTEER, 2));
        self::assertSame([], $volunteers->availability(self::VOLUNTEER, 1));
        self::assertTrue($volunteers->exceptions(self::VOLUNTEER, 2)[0]->available);

        self::assertTrue($teams->team(self::TEAM)?->active);
        self::assertSame([self::CREDENTIAL_TYPE], $teams->role(self::ROLE)?->requiredCredentials);
        self::assertSame('LEADER', $teams->role(self::ROLE)->childrenRole);
        self::assertTrue($teams->memberships(self::TEAM, self::VOLUNTEER)[0]->covers($from, $until));
        self::assertTrue($teams->approvals(self::ROLE, self::VOLUNTEER)[0]->covers($from, $until));

        self::assertSame(self::SESSION, $roster->shift(self::SHIFT)?->sourceId);
        self::assertSame([self::SHIFT], array_map(static fn ($s): string => $s->id, $roster->sourceShifts('CHILDREN_SESSION', self::SESSION, 10)));
        self::assertSame('CONFIRMED', $roster->assignment(self::ASSIGNMENT)?->status);
        self::assertSame([self::ASSIGNMENT], array_map(static fn ($a): string => $a->id, $roster->assignments(self::SHIFT)));
        self::assertCount(1, $roster->volunteerAssignments(self::VOLUNTEER, $from, $until));
        self::assertCount(0, $roster->volunteerAssignments(self::VOLUNTEER, $until, $until->modify('+1 hour')));
    }

    public function testDirectoryReportsReadyOnRealRowsAndNotReadyWhenCredentialIsRevoked(): void
    {
        $this->seed();
        $from = new DateTimeImmutable('2026-11-08T08:00:00Z');
        $until = new DateTimeImmutable('2026-11-08T10:00:00Z');

        $ready = $this->directory($from, $until)->assess(self::ASSIGNMENT, self::SESSION, 'LEADER', $from, $until);
        self::assertSame([], $ready->issues);
        self::assertTrue($ready->ready);

        $this->connection->executeStatement('UPDATE serving_credentials SET revoked_at = ?', ['2026-11-01 00:00:00+00']);
        $revoked = $this->directory($from, $until)->assess(self::ASSIGNMENT, self::SESSION, 'LEADER', $from, $until);
        self::assertSame(['Police clearance must be verified and valid through the end of this shift.'], $revoked->issues);
    }

    public function testRescheduledSessionAndOverlappingBookingAreReported(): void
    {
        $this->seed();
        $from = new DateTimeImmutable('2026-11-08T08:00:00Z');
        $until = new DateTimeImmutable('2026-11-08T10:00:00Z');
        $directory = $this->directory($from->modify('+30 minutes'), $until);
        $other = '10000000-0000-4000-8000-000000000009';
        $this->connection->executeStatement(
            "INSERT INTO serving_shifts (id, request_id, request_hash, team_id, role_id, source_kind, starts_at, ends_at, positions, status)
             VALUES (?, ?, ?, ?, ?, 'STANDALONE', ?, ?, 1, 'OPEN')",
            [$other, $other, str_repeat('a', 64), self::TEAM, self::ROLE, self::START, self::END],
        );
        $this->connection->executeStatement(
            "INSERT INTO serving_assignments (id, request_id, request_hash, shift_id, volunteer_id, status, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, 'PROPOSED', ?, now())",
            [$other, $other, str_repeat('a', 64), $other, self::VOLUNTEER, self::PERSON],
        );

        $result = $directory->assess(self::ASSIGNMENT, self::SESSION, 'LEADER', $from, $until);

        self::assertSame([
            'The linked activity was rescheduled. Review the duty and update the shift before proceeding.',
            'There is an overlapping assignment.',
        ], $result->issues);
    }

    public function testCandidatesListTheSessionsActiveAssignments(): void
    {
        $this->seed();
        $from = new DateTimeImmutable('2026-11-08T08:00:00Z');
        $until = new DateTimeImmutable('2026-11-08T10:00:00Z');

        $candidates = $this->directory($from, $until)->candidates(self::SESSION, $from, $until);

        self::assertCount(1, $candidates);
        self::assertTrue($candidates[0]->ready);
        self::assertSame([], $this->directory($from, $until)->candidates('10000000-0000-4000-8000-0000000000aa', $from, $until));
    }

    public function testAnAssignmentThatDoesNotExistIsUnavailable(): void
    {
        $this->seed();
        $from = new DateTimeImmutable('2026-11-08T08:00:00Z');

        $this->expectException(StaffingUnavailable::class);
        $this->directory($from, $from->modify('+1 hour'))->assess(self::TEAM, self::SESSION, 'LEADER', $from, $from->modify('+1 hour'));
    }

    private function directory(DateTimeImmutable $startsAt, DateTimeImmutable $endsAt): PublishedStaffingDirectory
    {
        $volunteers = new DbalVolunteerReader($this->connection);
        $teams = new DbalTeamReader($this->connection);
        $roster = new DbalRosterReader($this->connection);
        $people = new FakeDirectory([self::PERSON => true]);
        $source = new FakeActivitySource('CHILDREN_SESSION', [
            self::SESSION => new SourceActivity(self::SESSION, 'Sunday', $startsAt, $endsAt, true),
        ]);
        $readiness = new VolunteerReadiness($volunteers, $teams, $roster, $people, new ActivityRegistry([$source]));

        return new PublishedStaffingDirectory($roster, $volunteers, $teams, $readiness, $people);
    }

    private function seed(): void
    {
        $c = $this->connection;
        $revision = Fingerprint::of(
            self::SESSION,
            Fingerprint::instant(new DateTimeImmutable('2026-11-08T08:00:00Z')),
            Fingerprint::instant(new DateTimeImmutable('2026-11-08T10:00:00Z')),
        );
        $c->executeStatement("INSERT INTO serving_volunteers (id, person_id, status, availability_revision, created_at, updated_at) VALUES (?, ?, 'ACTIVE', 2, now(), now())", [self::VOLUNTEER, self::PERSON]);
        $c->executeStatement('INSERT INTO serving_teams (id, name) VALUES (?, ?)', [self::TEAM, 'Kids']);
        $c->executeStatement("INSERT INTO serving_roles (id, team_id, name, children_role) VALUES (?, ?, 'Lead', 'LEADER')", [self::ROLE, self::TEAM]);
        $c->executeStatement("INSERT INTO serving_credential_types (id, code, name) VALUES (?, 'police.clearance', 'Police clearance')", [self::CREDENTIAL_TYPE]);
        $c->executeStatement('INSERT INTO serving_role_requirements (role_id, type_id) VALUES (?, ?)', [self::ROLE, self::CREDENTIAL_TYPE]);
        $c->executeStatement('INSERT INTO serving_memberships (id, team_id, volunteer_id, valid_from, valid_until) VALUES (?, ?, ?, ?, ?)', [self::SHIFT, self::TEAM, self::VOLUNTEER, '2026-11-01 00:00:00+00', '2026-12-01 00:00:00+00']);
        $c->executeStatement('INSERT INTO serving_role_approvals (id, role_id, volunteer_id, valid_from, valid_until) VALUES (?, ?, ?, ?, ?)', [self::SHIFT, self::ROLE, self::VOLUNTEER, '2026-11-01 00:00:00+00', '2026-12-01 00:00:00+00']);
        $c->executeStatement('INSERT INTO serving_credentials (id, volunteer_id, type_id, valid_from, valid_until, evidence, verified_by, verified_at) VALUES (?, ?, ?, ?, ?, ?, ?, now())', [self::SHIFT, self::VOLUNTEER, self::CREDENTIAL_TYPE, '2026-01-01 00:00:00+00', '2027-01-01 00:00:00+00', 'Sighted', self::PERSON]);
        $c->executeStatement('INSERT INTO serving_availability_rules (id, volunteer_id, revision, day_of_week, starts_at, ends_at, time_zone, valid_from, valid_until) VALUES (?, ?, 2, 7, ?, ?, ?, ?, ?)', [self::SHIFT, self::VOLUNTEER, '09:00', '13:00', 'Africa/Johannesburg', '2026-01-01', '2027-01-01']);
        $c->executeStatement('INSERT INTO serving_availability_exceptions (id, volunteer_id, revision, starts_at, ends_at, available) VALUES (?, ?, 2, ?, ?, true)', [self::SHIFT, self::VOLUNTEER, self::START, self::END]);
        $c->executeStatement("INSERT INTO serving_shifts (id, request_id, request_hash, team_id, role_id, source_kind, source_id, source_revision, starts_at, ends_at, positions, status) VALUES (?, ?, ?, ?, ?, 'CHILDREN_SESSION', ?, ?, ?, ?, 1, 'OPEN')", [self::SHIFT, self::SHIFT, str_repeat('a', 64), self::TEAM, self::ROLE, self::SESSION, $revision, self::START, self::END]);
        $c->executeStatement("INSERT INTO serving_assignments (id, request_id, request_hash, shift_id, volunteer_id, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, 'CONFIRMED', ?, now())", [self::ASSIGNMENT, self::ASSIGNMENT, str_repeat('a', 64), self::SHIFT, self::VOLUNTEER, self::PERSON]);
    }
}
