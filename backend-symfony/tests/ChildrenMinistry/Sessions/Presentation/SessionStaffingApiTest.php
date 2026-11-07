<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Sessions\Presentation;

use App\Tests\ChildrenMinistry\Participation\Presentation\ChildrenApiTestCase;
use App\Volunteering\Application\Fingerprint;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Uuid;

final class SessionStaffingApiTest extends ChildrenApiTestCase
{
    private const string START = '2030-06-02T08:00:00Z';
    private const string END = '2030-06-02T10:00:00Z';

    private string $room;
    private string $group;
    private string $session;

    protected function setUp(): void
    {
        parent::setUp();
        $location = $this->create('/locations', ['name' => 'Main', 'timeZone' => 'Africa/Johannesburg']);
        $this->room = $this->create('/rooms', ['locationId' => $location, 'name' => 'Room 1', 'capacity' => 12]);
        $this->group = $this->create('/classes', ['programmeId' => $this->programme(), 'name' => 'Juniors', 'minAgeMonths' => 60, 'maxAgeMonths' => 156]);
        $this->session = $this->makeSession(self::START, self::END);
    }

    private function makeSession(string $start, string $end, ?string $room = null): string
    {
        return $this->create('/sessions', ['classId' => $this->group, 'roomId' => $room ?? $this->room, 'startsAt' => $start, 'endsAt' => $end, 'seatLimit' => 10]);
    }

    /** @return array{string, string} person id and account id of an adult with a station account */
    private function adult(string $name): array
    {
        $person = $this->person($name, $this->yearsAgo(30));

        return [$person, $this->accountWith(strtolower($name), ['children.station'])];
    }

    /** @return array<string, mixed> */
    private function workerBody(string $person, string $account, string $until = '+1 year'): array
    {
        $at = static fn (string $modify): string => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify($modify)->format('Y-m-d\TH:i:s\Z');

        return ['personId' => $person, 'accountId' => $account, 'screenedUntil' => $at($until), 'trainedUntil' => $at($until), 'evidenceReference' => 'Police clearance'];
    }

    private function register(string $name): string
    {
        [$person, $account] = $this->adult($name);
        $this->call('POST', '/api/children/workers', $this->admin, $this->workerBody($person, $account, '+20 years'));
        self::assertSame(204, $this->code());

        return $person;
    }

    /** @param array<string, mixed> $body */
    private function setStaff(array $body, ?string $token = null): void
    {
        $this->call('PUT', '/api/children/sessions/staff', $token ?? $this->admin, $body + ['sessionId' => $this->session, 'expectedVersion' => 0, 'role' => 'LEADER', 'present' => true]);
    }

    public function testRegisteringAWorkerIsAuditedAndReadBack(): void
    {
        [$person, $account] = $this->adult('Thandi');

        $this->call('POST', '/api/children/workers', $this->admin, $this->workerBody($person, $account));
        self::assertSame(204, $this->code());

        self::assertSame(1, $this->rows('children_audit', "action = 'worker.verified' AND target_id = '$person' AND actor_id = '{$this->adminId}'"));
        $this->call('GET', "/api/children/workers/$person", $this->admin);
        self::assertSame(200, $this->code());
        $worker = $this->json();
        self::assertSame([$person, $account, 'Police clearance', $this->adminId, null, 0], [$worker['personId'], $worker['accountId'], $worker['evidenceReference'], $worker['verifiedBy'], $worker['revokedAt'], $worker['version']]);
        self::assertMatchesRegularExpression('/\A\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d(\.\d+)?Z\z/', $worker['verifiedFrom']);
    }

    public function testRegistrationRulesAreEnforced(): void
    {
        [$person, $account] = $this->adult('Thandi');
        $child = $this->person('Kid', $this->yearsAgo(10));
        $kidAccount = $this->accountWith('kid', []);
        $suspended = $this->account('suspended', status: 'disabled');
        foreach ([
            [['personId' => $child, 'accountId' => $kidAccount] + $this->workerBody($person, $account), 400, 'A recorded birth date confirming adulthood is required.'],
            [['accountId' => $suspended] + $this->workerBody($person, $account), 400, 'Link an active account to the verified worker.'],
            [['personId' => Uuid::v4()->toRfc4122()] + $this->workerBody($person, $account), 404, null],
            [['screenedUntil' => '2020-01-01T00:00:00Z'] + $this->workerBody($person, $account), 400, 'Validity end must follow its start.'],
            [['evidenceReference' => ''] + $this->workerBody($person, $account), 400, null],
            [['evidenceReference' => str_repeat('x', 2001)] + $this->workerBody($person, $account), 400, null],
            [['trainedUntil' => null] + $this->workerBody($person, $account), 400, null],
            [['accountId' => 'nope'] + $this->workerBody($person, $account), 400, null],
        ] as [$body, $status, $message]) {
            $this->call('POST', '/api/children/workers', $this->admin, $body);
            self::assertSame($status, $this->code(), json_encode($body, JSON_THROW_ON_ERROR));
            if (null !== $message) {
                self::assertSame($message, $this->message());
            }
        }
        self::assertSame(0, $this->rows('children_workers'));

        $this->call('POST', '/api/children/workers', $this->admin, $this->workerBody($person, $account));
        self::assertSame(204, $this->code());
        $this->call('POST', '/api/children/workers', $this->admin, $this->workerBody($person, $account));
        self::assertSame(409, $this->code(), 'a person is registered once');
    }

    public function testReviewingRenewsAndRevokesWithVersions(): void
    {
        [$person, $account] = $this->adult('Thandi');
        $this->call('POST', '/api/children/workers', $this->admin, $this->workerBody($person, $account));
        $review = ['personId' => $person, 'expectedVersion' => 0, 'screenedUntil' => '2090-01-01T00:00:00Z', 'trainedUntil' => '2090-06-01T00:00:00Z', 'evidenceReference' => 'Renewed', 'revoked' => false];

        $this->call('PUT', '/api/children/workers', $this->admin, ['expectedVersion' => 4] + $review);
        self::assertSame(409, $this->code());
        $this->call('PUT', '/api/children/workers', $this->admin, $review);
        self::assertSame(204, $this->code());
        $this->call('GET', "/api/children/workers/$person", $this->admin);
        self::assertSame(['2090-01-01T00:00:00Z', 'Renewed', 1, null], [$this->json()['screenedUntil'], $this->json()['evidenceReference'], $this->json()['version'], $this->json()['revokedAt']]);

        $this->call('PUT', '/api/children/workers', $this->admin, ['expectedVersion' => 1, 'revoked' => true] + $review);
        self::assertSame(204, $this->code());
        $this->call('GET', "/api/children/workers/$person", $this->admin);
        self::assertNotNull($this->json()['revokedAt']);
        self::assertSame(2, $this->json()['version']);
        self::assertSame(2, $this->rows('children_audit', "action = 'worker.reviewed' AND target_id = '$person'"));

        $this->call('PUT', '/api/children/workers', $this->admin, ['personId' => Uuid::v4()->toRfc4122()] + $review);
        self::assertSame(404, $this->code());
    }

    public function testRenewingNeedsAnActiveAccount(): void
    {
        [$person, $account] = $this->adult('Thandi');
        $this->call('POST', '/api/children/workers', $this->admin, $this->workerBody($person, $account));
        $this->connection->executeStatement("UPDATE user_accounts SET status = 'disabled' WHERE id = ?", [$account]);
        $review = ['personId' => $person, 'expectedVersion' => 0, 'screenedUntil' => '2090-01-01T00:00:00Z', 'trainedUntil' => '2090-06-01T00:00:00Z', 'evidenceReference' => 'Renewed'];

        $this->call('PUT', '/api/children/workers', $this->admin, $review);
        self::assertSame(400, $this->code());
        self::assertSame('Worker account is inactive.', $this->message());
        $this->call('PUT', '/api/children/workers', $this->admin, ['revoked' => true] + $review);
        self::assertSame(204, $this->code(), 'an inactive account can still be revoked');
    }

    public function testWorkerRoutesNeedTheSafeguardingPermission(): void
    {
        [$person, $account] = $this->adult('Thandi');
        $this->accountWith('manager', ['children.manage', 'children.read']);
        $manager = $this->login('manager');
        foreach ([['POST', '/api/children/workers', $this->workerBody($person, $account)], ['PUT', '/api/children/workers', $this->workerBody($person, $account) + ['expectedVersion' => 0]], ['GET', "/api/children/workers/$person", null]] as [$method, $path, $body]) {
            $this->call($method, $path, null, $body);
            self::assertSame(401, $this->code());
            foreach ([$this->reader, $manager] as $token) {
                $this->call($method, $path, $token, $body);
                self::assertSame(403, $this->code(), "$method $path");
            }
        }
        self::assertSame(0, $this->rows('children_workers'));
        $this->call('GET', '/api/children/workers/'.Uuid::v4()->toRfc4122(), $this->admin);
        self::assertSame(404, $this->code());
    }

    public function testStaffingASessionAndTheOpenSessionFallingIntoReview(): void
    {
        $leader = $this->register('Lead');
        $this->setStaff(['workerId' => $leader]);
        self::assertSame(204, $this->code());
        self::assertSame(1, $this->rows('children_audit', "action = 'session.staff.changed' AND target_id = '{$this->session}' AND reason = '$leader:LEADER:present=true'"));

        $this->call('PUT', '/api/children/sessions/status', $this->admin, ['sessionId' => $this->session, 'expectedVersion' => 1, 'status' => 'OPEN', 'reason' => 'Start']);
        self::assertSame(204, $this->code());
        $this->setStaff(['workerId' => $leader, 'present' => false, 'expectedVersion' => 2]);
        self::assertSame(204, $this->code());

        $this->call('GET', "/api/children/sessions/{$this->session}", $this->admin);
        $view = $this->json();
        self::assertSame(['NEEDS_REVIEW', 3], [$view['session']['status'], $view['session']['version']]);
        self::assertSame([[$leader, 'LEADER', false]], array_map(static fn (array $staff): array => [$staff['personId'], $staff['role'], $staff['present']], $view['staff']));
    }

    public function testStaffingRulesAreEnforced(): void
    {
        $leader = $this->register('Lead');
        $this->setStaff(['workerId' => Uuid::v4()->toRfc4122()]);
        self::assertSame(404, $this->code());
        $this->setStaff(['workerId' => $leader, 'expectedVersion' => 5]);
        self::assertSame(409, $this->code());
        foreach ([['role' => 'COOK'], ['role' => null], ['sessionId' => 'x'], ['workerId' => 'x'], ['expectedVersion' => -1]] as $change) {
            $this->setStaff($change + ['workerId' => $leader]);
            self::assertSame(400, $this->code(), json_encode($change, JSON_THROW_ON_ERROR));
        }
        $this->setStaff(['workerId' => $leader, 'sessionId' => Uuid::v4()->toRfc4122()]);
        self::assertSame(404, $this->code());
        self::assertSame(0, $this->rows('children_session_staff'));

        $this->call('PUT', '/api/children/sessions/status', $this->admin, ['sessionId' => $this->session, 'expectedVersion' => 0, 'status' => 'CANCELLED', 'reason' => 'Rain']);
        $this->setStaff(['workerId' => $leader, 'expectedVersion' => 1]);
        self::assertSame(409, $this->code());
        self::assertSame('Terminal session staffing cannot change.', $this->message());
    }

    public function testAWorkerCannotBePresentInTwoOverlappingSessions(): void
    {
        $leader = $this->register('Lead');
        $otherRoom = $this->create('/rooms', ['locationId' => $this->connection->fetchOne('SELECT location_id FROM children_rooms WHERE id = ?', [$this->room]), 'name' => 'Room 2', 'capacity' => 12]);
        $other = $this->create('/classes', ['programmeId' => $this->programme('Other'), 'name' => 'Seniors', 'minAgeMonths' => 120, 'maxAgeMonths' => 200]);
        $overlapping = $this->create('/sessions', ['classId' => $other, 'roomId' => $otherRoom, 'startsAt' => '2030-06-02T09:00:00Z', 'endsAt' => '2030-06-02T11:00:00Z', 'seatLimit' => 5]);
        $this->setStaff(['workerId' => $leader]);
        self::assertSame(204, $this->code());

        $this->setStaff(['workerId' => $leader, 'sessionId' => $overlapping]);
        self::assertSame(409, $this->code());
        self::assertSame('Worker is already present in an overlapping session. Mark them absent there first.', $this->message());
        $this->setStaff(['workerId' => $leader, 'sessionId' => $overlapping, 'present' => false]);
        self::assertSame(204, $this->code(), 'absent is always allowed');
        $this->setStaff(['workerId' => $leader, 'present' => false, 'expectedVersion' => 1]);
        $this->setStaff(['workerId' => $leader, 'sessionId' => $overlapping, 'expectedVersion' => 1]);
        self::assertSame(204, $this->code(), 'once absent from the first, present in the second');
    }

    public function testStaffingRoutesNeedChildrenManage(): void
    {
        $this->accountWith('station', ['children.station', 'children.safeguarding']);
        $token = $this->login('station');
        foreach ([['PUT', '/api/children/sessions/staff', ['sessionId' => $this->session, 'workerId' => $this->session, 'expectedVersion' => 0, 'role' => 'LEADER']], ['GET', "/api/children/sessions/{$this->session}/serving-candidates", null], ['POST', "/api/children/sessions/{$this->session}/serving-staff", ['expectedVersion' => 0, 'assignmentId' => $this->session, 'responsibility' => 'LEADER']]] as [$method, $path, $body]) {
            $this->call($method, $path, null, $body);
            self::assertSame(401, $this->code());
            foreach ([$this->reader, $token] as $who) {
                $this->call($method, $path, $who, $body);
                self::assertSame(403, $this->code(), "$method $path");
            }
        }
    }

    public function testCandidatesAreEmptyWithoutAssignmentsAndMalformedIdsAre400(): void
    {
        $this->call('GET', "/api/children/sessions/{$this->session}/serving-candidates", $this->admin);
        self::assertSame([200, []], [$this->code(), $this->json()]);
        $this->call('GET', '/api/children/sessions/'.Uuid::v4()->toRfc4122().'/serving-candidates', $this->admin);
        self::assertSame(404, $this->code());
        $this->call('GET', '/api/children/sessions/nope/serving-candidates', $this->admin);
        self::assertSame(400, $this->code());
    }

    /** Seeds a confirmed, ready LEADER assignment of the person on this session; returns the assignment id. */
    private function volunteerAssignment(string $person): string
    {
        $c = $this->connection;
        [$volunteer, $team, $role, $type, $shift, $assignment] = array_map(static fn (): string => Uuid::v4()->toRfc4122(), range(1, 6));
        $revision = Fingerprint::of($this->session, Fingerprint::instant(new DateTimeImmutable(self::START)), Fingerprint::instant(new DateTimeImmutable(self::END)));
        $c->executeStatement("INSERT INTO serving_volunteers (id, person_id, status, availability_revision, created_at, updated_at) VALUES (?, ?, 'ACTIVE', 2, now(), now())", [$volunteer, $person]);
        $c->executeStatement('INSERT INTO serving_teams (id, name) VALUES (?, ?)', [$team, 'Kids']);
        $c->executeStatement("INSERT INTO serving_roles (id, team_id, name, children_role) VALUES (?, ?, 'Lead', 'LEADER')", [$role, $team]);
        $c->executeStatement("INSERT INTO serving_credential_types (id, code, name) VALUES (?, 'police.clearance', 'Police clearance')", [$type]);
        $c->executeStatement('INSERT INTO serving_role_requirements (role_id, type_id) VALUES (?, ?)', [$role, $type]);
        $c->executeStatement('INSERT INTO serving_memberships (id, team_id, volunteer_id, valid_from, valid_until) VALUES (?, ?, ?, ?, ?)', [Uuid::v4()->toRfc4122(), $team, $volunteer, '2026-01-01 00:00:00+00', '2040-01-01 00:00:00+00']);
        $c->executeStatement('INSERT INTO serving_role_approvals (id, role_id, volunteer_id, valid_from, valid_until) VALUES (?, ?, ?, ?, ?)', [Uuid::v4()->toRfc4122(), $role, $volunteer, '2026-01-01 00:00:00+00', '2040-01-01 00:00:00+00']);
        $c->executeStatement('INSERT INTO serving_credentials (id, volunteer_id, type_id, valid_from, valid_until, evidence, verified_by, verified_at) VALUES (?, ?, ?, ?, ?, ?, ?, now())', [Uuid::v4()->toRfc4122(), $volunteer, $type, '2026-01-01 00:00:00+00', '2040-01-01 00:00:00+00', 'Sighted', $person]);
        $c->executeStatement('INSERT INTO serving_availability_rules (id, volunteer_id, revision, day_of_week, starts_at, ends_at, time_zone, valid_from, valid_until) VALUES (?, ?, 2, 7, ?, ?, ?, ?, ?)', [Uuid::v4()->toRfc4122(), $volunteer, '09:00', '13:00', 'Africa/Johannesburg', '2026-01-01', '2040-01-01']);
        $c->executeStatement('INSERT INTO serving_availability_exceptions (id, volunteer_id, revision, starts_at, ends_at, available) VALUES (?, ?, 2, ?, ?, true)', [Uuid::v4()->toRfc4122(), $volunteer, '2030-06-02 08:00:00+00', '2030-06-02 10:00:00+00']);
        $c->executeStatement("INSERT INTO serving_shifts (id, request_id, request_hash, team_id, role_id, source_kind, source_id, source_revision, starts_at, ends_at, positions, status) VALUES (?, ?, ?, ?, ?, 'CHILDREN_SESSION', ?, ?, ?, ?, 1, 'OPEN')", [$shift, $shift, str_repeat('a', 64), $team, $role, $this->session, $revision, '2030-06-02 08:00:00+00', '2030-06-02 10:00:00+00']);
        $c->executeStatement("INSERT INTO serving_assignments (id, request_id, request_hash, shift_id, volunteer_id, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, 'CONFIRMED', ?, now())", [$assignment, $assignment, str_repeat('a', 64), $shift, $volunteer, $person]);

        return $assignment;
    }

    public function testAReadyServingAssignmentIsListedImportedAndLinked(): void
    {
        [$person, $account] = $this->adult('Thandi');
        $assignment = $this->volunteerAssignment($person);

        $this->call('GET', "/api/children/sessions/{$this->session}/serving-candidates", $this->admin);
        self::assertSame(200, $this->code(), $this->message());
        self::assertSame([['assignment' => ['assignmentId' => $assignment, 'personId' => $person, 'name' => $this->json()[0]['assignment']['name'], 'responsibility' => 'LEADER', 'ready' => true, 'issues' => []], 'workerRegistered' => false]], $this->json());

        $import = ['expectedVersion' => 0, 'assignmentId' => $assignment, 'responsibility' => 'LEADER', 'present' => true];
        $this->call('POST', "/api/children/sessions/{$this->session}/serving-staff", $this->admin, $import);
        self::assertSame(409, $this->code());
        self::assertSame('Register and verify this person as a Children Ministry worker in Spaces & team first.', $this->message());

        $this->call('POST', '/api/children/workers', $this->admin, $this->workerBody($person, $account, '+20 years'));
        self::assertSame(204, $this->code());
        $this->call('GET', "/api/children/sessions/{$this->session}/serving-candidates", $this->admin);
        self::assertTrue($this->json()[0]['workerRegistered']);

        $this->call('POST', "/api/children/sessions/{$this->session}/serving-staff", $this->admin, ['responsibility' => 'COOK'] + $import);
        self::assertSame(409, $this->code(), 'the assignment is not approved for that responsibility');
        $this->call('POST', "/api/children/sessions/{$this->session}/serving-staff", $this->admin, $import);
        self::assertSame(204, $this->code());

        self::assertSame(1, $this->rows('children_volunteer_staff_links', "session_id = '{$this->session}' AND worker_id = '$person' AND assignment_id = '$assignment' AND role = 'LEADER'"));
        $this->call('GET', "/api/children/sessions/{$this->session}", $this->admin);
        $view = $this->json();
        self::assertSame([[$person, 'LEADER', true, []]], array_map(static fn (array $staff): array => [$staff['personId'], $staff['role'], $staff['present'], $staff['issues']], $view['staff']));
        self::assertSame([1, 2], [$view['session']['version'], $view['effectiveCapacity']]);

        $this->call('POST', "/api/children/sessions/{$this->session}/serving-staff", $this->admin, $import);
        self::assertSame(409, $this->code(), 'the session version moved on');
    }

    public function testAnAssignmentThatIsNotReadyIsRefusedWithItsIssues(): void
    {
        [$person, $account] = $this->adult('Thandi');
        $assignment = $this->volunteerAssignment($person);
        $this->call('POST', '/api/children/workers', $this->admin, $this->workerBody($person, $account, '+20 years'));
        $this->connection->executeStatement('UPDATE serving_credentials SET revoked_at = now()');

        $this->call('POST', "/api/children/sessions/{$this->session}/serving-staff", $this->admin, ['expectedVersion' => 0, 'assignmentId' => $assignment, 'responsibility' => 'LEADER', 'present' => true]);

        self::assertSame(409, $this->code());
        self::assertStringContainsString('Police clearance', $this->message());
        self::assertSame(0, $this->rows('children_volunteer_staff_links'));
        $this->call('POST', "/api/children/sessions/{$this->session}/serving-staff", $this->admin, ['expectedVersion' => 0, 'assignmentId' => Uuid::v4()->toRfc4122(), 'responsibility' => 'LEADER', 'present' => true]);
        self::assertSame(409, $this->code());
        self::assertSame('The serving assignment is no longer available. Refresh the roster.', $this->message());
        $this->call('POST', "/api/children/sessions/{$this->session}/serving-staff", $this->admin, ['expectedVersion' => 0, 'assignmentId' => 'x', 'responsibility' => '']);
        self::assertSame(400, $this->code());
    }
}
