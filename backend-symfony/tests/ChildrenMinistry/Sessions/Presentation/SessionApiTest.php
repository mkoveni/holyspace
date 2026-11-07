<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Sessions\Presentation;

use App\Tests\ChildrenMinistry\Participation\Presentation\ChildrenApiTestCase;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Uuid;

final class SessionApiTest extends ChildrenApiTestCase
{
    private string $location;
    private string $room;
    private string $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->location = $this->create('/locations', ['name' => 'Main campus', 'timeZone' => 'Africa/Johannesburg']);
        $this->room = $this->create('/rooms', ['locationId' => $this->location, 'name' => 'Room 1', 'capacity' => 12]);
        $this->group = $this->create('/classes', ['programmeId' => $this->programme(), 'name' => 'Juniors', 'minAgeMonths' => 60, 'maxAgeMonths' => 156]);
    }

    private function at(string $modify): string
    {
        return (new DateTimeImmutable('2030-06-01T09:00:00', new DateTimeZone('UTC')))->modify($modify)->format('Y-m-d\TH:i:s\Z');
    }

    /** @return array<string, mixed> */
    private function body(string $start = '+0 hours', string $end = '+2 hours', int $seats = 10): array
    {
        return ['classId' => $this->group, 'roomId' => $this->room, 'startsAt' => $this->at($start), 'endsAt' => $this->at($end), 'seatLimit' => $seats];
    }

    private function session(string $start = '+0 hours', string $end = '+2 hours'): string
    {
        return $this->create('/sessions', $this->body($start, $end));
    }

    /** Registers a cleared adult as a worker (workers are registered through the API in C5) and puts them on the session. */
    private function staff(string $session, string $role = 'LEADER', bool $present = true, string $username = 'leader'): string
    {
        $person = $this->person(ucfirst($username), $this->yearsAgo(30));
        $account = $this->accountWith($username, ['children.station']);
        $this->connection->insert('children_workers', [
            'person_id' => $person, 'account_id' => $account, 'verified_from' => '2026-01-01 00:00:00+00', 'screened_until' => '2035-01-01 00:00:00+00',
            'trained_until' => '2035-01-01 00:00:00+00', 'evidence_reference' => 'Checked', 'verified_by' => $this->adminId, 'version' => 0,
        ]);
        $this->connection->insert('children_session_staff', ['session_id' => $session, 'worker_id' => $person, 'role' => $role, 'present' => $present ? 'true' : 'false']);

        return $account;
    }

    /** @param array<string, mixed>|null $change */
    private function changeStatus(string $session, string $status, int $version = 0, ?string $token = null, ?array $change = null): void
    {
        $this->call('PUT', '/api/children/sessions/status', $token ?? $this->admin, ($change ?? []) + ['sessionId' => $session, 'expectedVersion' => $version, 'status' => $status, 'reason' => 'Because']);
    }

    public function testCreatingASessionPlansItAuditsItAndReadsBack(): void
    {
        $id = $this->session();

        self::assertSame(1, $this->rows('children_audit', "action = 'session.created' AND target_id = '$id' AND actor_id = '{$this->adminId}'"));
        $this->call('GET', "/api/children/sessions/$id", $this->reader);
        self::assertSame(200, $this->code());
        $view = $this->json();
        self::assertSame(['id' => $id, 'classId' => $this->group, 'roomId' => $this->room, 'startsAt' => '2030-06-01T09:00:00Z', 'endsAt' => '2030-06-01T11:00:00Z', 'seatLimit' => 10, 'status' => 'PLANNED', 'version' => 0], $view['session']);
        self::assertSame(['Juniors', 'Room 1', 'Africa/Johannesburg', 0, 0, true, []], [$view['className'], $view['roomName'], $view['timeZone'], $view['effectiveCapacity'], $view['occupancy'], $view['staffingNeedsReview'], $view['staff']]);
    }

    public function testCreationRulesAreEnforced(): void
    {
        foreach ([
            [['seatLimit' => 13] + $this->body(), 400, 'Session seats cannot exceed room capacity.'],
            [['seatLimit' => 0] + $this->body(), 400, null],
            [['seatLimit' => 10001] + $this->body(), 400, null],
            [['endsAt' => $this->at('+0 hours')] + $this->body(), 400, 'Validity end must follow its start.'],
            [['endsAt' => $this->at('-1 hours')] + $this->body(), 400, 'Validity end must follow its start.'],
            [['startsAt' => 'tomorrow'] + $this->body(), 400, null],
            [['classId' => 'nope'] + $this->body(), 400, null],
            [['classId' => Uuid::v4()->toRfc4122()] + $this->body(), 404, 'MinistryClass was not found.'],
            [['roomId' => Uuid::v4()->toRfc4122()] + $this->body(), 404, 'Room was not found.'],
            [array_diff_key($this->body(), ['roomId' => 1]), 400, null],
        ] as [$body, $status, $message]) {
            $this->call('POST', '/api/children/sessions', $this->admin, $body);
            self::assertSame($status, $this->code(), json_encode($body, JSON_THROW_ON_ERROR));
            if (null !== $message) {
                self::assertSame($message, $this->message());
            }
        }
        self::assertSame(0, $this->rows('children_sessions'));
    }

    public function testASessionCannotOverlapAnotherInTheSameRoomOrClass(): void
    {
        $this->session('+0 hours', '+2 hours');

        $secondRoom = $this->create('/rooms', ['locationId' => $this->location, 'name' => 'Room 2', 'capacity' => 12]);
        $secondClass = $this->create('/classes', ['programmeId' => $this->programme('Other'), 'name' => 'Seniors', 'minAgeMonths' => 120, 'maxAgeMonths' => 200]);

        $this->call('POST', '/api/children/sessions', $this->admin, $this->body('+1 hours', '+3 hours'));
        self::assertSame(409, $this->code(), 'same room and class');
        $this->call('POST', '/api/children/sessions', $this->admin, ['roomId' => $secondRoom] + $this->body('+1 hours', '+3 hours'));
        self::assertSame(409, $this->code(), 'same class in another room');
        $this->call('POST', '/api/children/sessions', $this->admin, ['classId' => $secondClass] + $this->body('+1 hours', '+3 hours'));
        self::assertSame(409, $this->code(), 'another class in the same room');
        $this->call('POST', '/api/children/sessions', $this->admin, ['roomId' => $secondRoom, 'classId' => $secondClass] + $this->body('+1 hours', '+3 hours'));
        self::assertSame(201, $this->code(), 'a different room and class may overlap');
        $this->call('POST', '/api/children/sessions', $this->admin, $this->body('+2 hours', '+4 hours'));
        self::assertSame(201, $this->code(), 'back to back is not an overlap');
    }

    public function testACancelledSessionNoLongerBlocksItsSlot(): void
    {
        $first = $this->session();
        $this->changeStatus($first, 'CANCELLED');
        self::assertSame(204, $this->code());

        $this->call('POST', '/api/children/sessions', $this->admin, $this->body());

        self::assertSame(201, $this->code());
    }

    public function testRoutesNeedTheirPermissions(): void
    {
        $id = $this->session();
        $this->accountWith('station', ['children.station']);
        $this->accountWith('manager', ['children.manage']);
        $holders = ['children.manage' => $this->login('manager'), 'children.read' => $this->reader, 'children.station' => $this->login('station')];
        $calls = [
            ['POST', '/api/children/sessions', $this->body(), 'children.manage'],
            ['PUT', '/api/children/sessions/status', ['sessionId' => $id, 'expectedVersion' => 0, 'status' => 'CANCELLED', 'reason' => 'x'], 'children.manage'],
            ['GET', '/api/children/sessions', null, 'children.read'],
            ['GET', "/api/children/sessions/$id", null, 'children.read'],
            ['GET', '/api/children/station/sessions', null, 'children.station'],
            ['GET', "/api/children/station/sessions/$id", null, 'children.station'],
        ];
        foreach ($calls as [$method, $path, $body, $needed]) {
            $this->call($method, $path, null, $body);
            self::assertSame(401, $this->code(), "anonymous $path");
            foreach ($holders as $permission => $token) {
                if ($permission === $needed || ('children.read' === $needed && 'children.manage' === $permission)) {
                    if (!str_contains($path, '/station/sessions/')) { // the station detail also needs a present assignment
                        $this->call($method, $path, $token, $body);
                        self::assertNotSame(403, $this->code(), "$permission may $method $path");
                    }
                    continue;
                }
                $this->call($method, $path, $token, $body);
                self::assertSame(403, $this->code(), "$permission may not $method $path");
            }
        }
    }

    public function testListingIsOrderedFilteredByLocationAndLimitedTo31Days(): void
    {
        $later = $this->session('+1 days', '+1 days +2 hours');
        $first = $this->session();
        $otherLocation = $this->create('/locations', ['name' => 'Annex', 'timeZone' => 'Africa/Johannesburg']);
        $annexRoom = $this->create('/rooms', ['locationId' => $otherLocation, 'name' => 'Hall', 'capacity' => 30]);
        $annex = $this->create('/sessions', ['roomId' => $annexRoom, 'startsAt' => $this->at('+3 hours'), 'endsAt' => $this->at('+5 hours'), 'seatLimit' => 20] + $this->body());
        $window = 'from='.rawurlencode($this->at('-1 hours')).'&until='.rawurlencode($this->at('+3 days'));

        $this->call('GET', "/api/children/sessions?$window", $this->reader);
        self::assertSame(200, $this->code());
        self::assertSame([$first, $annex, $later], array_map(static fn (array $view): string => $view['session']['id'], $this->json()));

        $this->call('GET', "/api/children/sessions?$window&locationId=$otherLocation", $this->reader);
        self::assertSame([$annex], array_map(static fn (array $view): string => $view['session']['id'], $this->json()));

        $this->call('GET', "/api/children/sessions?$window&limit=1&offset=1", $this->reader);
        self::assertSame([$annex], array_map(static fn (array $view): string => $view['session']['id'], $this->json()));

        $this->call('GET', '/api/children/sessions?from='.rawurlencode($this->at('+0 hours')).'&until='.rawurlencode($this->at('+0 hours')), $this->reader);
        self::assertSame(400, $this->code());
        $this->call('GET', '/api/children/sessions?from='.rawurlencode($this->at('+0 hours')).'&until='.rawurlencode($this->at('+32 days')), $this->reader);
        self::assertSame(400, $this->code());
        self::assertSame('Search at most 31 days at a time.', $this->message());
        $this->call('GET', '/api/children/sessions?from='.rawurlencode($this->at('+0 hours')).'&until='.rawurlencode($this->at('+31 days')), $this->reader);
        self::assertSame(200, $this->code(), 'exactly 31 days is allowed');
        foreach (['limit=0', 'limit=101', 'offset=-1', 'limit=abc', 'locationId=zzz', 'from=nope'] as $query) {
            $this->call('GET', "/api/children/sessions?$query", $this->reader);
            self::assertSame(400, $this->code(), $query);
        }
    }

    public function testWithoutAWindowTheDefaultIsADayAgoForAWeek(): void
    {
        $recent = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $near = $this->create('/sessions', ['startsAt' => $recent->modify('+1 day')->format('Y-m-d\TH:i:s\Z'), 'endsAt' => $recent->modify('+1 day +1 hour')->format('Y-m-d\TH:i:s\Z')] + $this->body());
        $this->create('/sessions', ['startsAt' => $recent->modify('+9 days')->format('Y-m-d\TH:i:s\Z'), 'endsAt' => $recent->modify('+9 days +1 hour')->format('Y-m-d\TH:i:s\Z')] + $this->body());

        $this->call('GET', '/api/children/sessions', $this->reader);

        self::assertSame([$near], array_map(static fn (array $view): string => $view['session']['id'], $this->json()));
    }

    public function testGettingAnUnknownOrMalformedSessionFails(): void
    {
        $this->call('GET', '/api/children/sessions/'.Uuid::v4()->toRfc4122(), $this->reader);
        self::assertSame(404, $this->code());
        $this->call('GET', '/api/children/sessions/not-a-uuid', $this->reader);
        self::assertSame(400, $this->code());
    }

    public function testOpeningNeedsALeaderAndTheViewShowsStaffAndCapacity(): void
    {
        $id = $this->session();
        $this->changeStatus($id, 'OPEN');
        self::assertSame(409, $this->code());
        self::assertSame('Opening requires eligible present staff, including a leader, and sufficient capacity.', $this->message());

        $this->staff($id, 'ASSISTANT', username: 'helper');
        $this->changeStatus($id, 'OPEN');
        self::assertSame(409, $this->code(), 'an assistant alone is not enough');

        $this->staff($id, 'LEADER', username: 'leader');
        $this->changeStatus($id, 'OPEN');
        self::assertSame(204, $this->code());

        $this->call('GET', "/api/children/sessions/$id", $this->reader);
        $view = $this->json();
        self::assertSame(['OPEN', 1], [$view['session']['status'], $view['session']['version']]);
        self::assertSame([4, false], [$view['effectiveCapacity'], $view['staffingNeedsReview']]);
        self::assertSame([['LEADER', true, []], ['ASSISTANT', true, []]], array_map(static fn (array $staff): array => [$staff['role'], $staff['present'], $staff['issues']], $this->sortedStaff($view)));
        self::assertSame(1, $this->rows('children_audit', "action = 'session.status.changed' AND target_id = '$id' AND reason = 'Because'"));
    }

    /**
     * @param array<mixed> $view
     *
     * @return list<array<string, mixed>>
     */
    private function sortedStaff(array $view): array
    {
        $staff = $view['staff'];
        usort($staff, static fn (array $a, array $b): int => strcmp($a['role'], $b['role']));

        return array_reverse($staff);
    }

    public function testStatusChangeValidatesItsInput(): void
    {
        $id = $this->session();
        foreach ([
            ['status' => 'SIDEWAYS'],
            ['status' => null],
            ['reason' => ''],
            ['reason' => null],
            ['reason' => str_repeat('x', 501)],
            ['expectedVersion' => -1],
            ['sessionId' => 'nope'],
        ] as $change) {
            $this->changeStatus($id, 'CANCELLED', change: $change);
            self::assertSame(400, $this->code(), json_encode($change, JSON_THROW_ON_ERROR));
        }
        $this->changeStatus(Uuid::v4()->toRfc4122(), 'CANCELLED');
        self::assertSame(404, $this->code());
        self::assertSame('PLANNED', $this->connection->fetchOne('SELECT status FROM children_sessions WHERE id = ?', [$id]));
    }

    public function testVersionsAndTerminalStatesAreEnforced(): void
    {
        $id = $this->session();
        $this->changeStatus($id, 'CANCELLED', 4);
        self::assertSame(409, $this->code());
        $this->changeStatus($id, 'CANCELLED', 0);
        self::assertSame(204, $this->code());
        $this->changeStatus($id, 'OPEN', 1);
        self::assertSame(409, $this->code());
        $this->changeStatus($id, 'PLANNED', 1);
        self::assertSame(409, $this->code());
        self::assertSame('This session is terminal and cannot be reopened.', $this->message());
        self::assertSame(1, $this->rows('children_sessions', "id = '$id' AND status = 'CANCELLED' AND version = 1"));
    }

    public function testChildrenInTheRoomBlockClosingAndCancelling(): void
    {
        $id = $this->session();
        $this->staff($id);
        $this->changeStatus($id, 'OPEN');
        $child = $this->person('Little', $this->yearsAgo(8));
        $this->connection->executeStatement('SET session_replication_role = replica');
        $this->connection->insert('children_attendance', ['id' => Uuid::v4()->toRfc4122(), 'child_id' => $child, 'session_id' => $id, 'arrived_at' => '2030-06-01 09:05:00+00', 'arrived_by' => $this->adminId, 'arrival_request_id' => Uuid::v4()->toRfc4122()]);
        $this->connection->executeStatement('SET session_replication_role = DEFAULT');

        foreach (['CLOSED', 'CANCELLED'] as $status) {
            $this->changeStatus($id, $status, 1);
            self::assertSame(409, $this->code());
            self::assertSame('Collect or transfer all children before closing or cancelling.', $this->message());
        }
        $this->call('GET', "/api/children/sessions/$id", $this->reader);
        self::assertSame(1, $this->json()['occupancy']);
        self::assertSame('OPEN', $this->json()['session']['status']);
    }

    public function testOpenIncidentsNeedAnAcknowledgementFromSomeoneWhoManagesThem(): void
    {
        $id = $this->session();
        $child = $this->person('Little', $this->yearsAgo(8));
        $incident = Uuid::v4()->toRfc4122();
        $this->connection->executeStatement('SET session_replication_role = replica');
        $this->connection->insert('children_incidents', [
            'id' => $incident, 'child_id' => $child, 'session_id' => $id, 'segment_id' => Uuid::v4()->toRfc4122(), 'category' => 'INJURY', 'severity' => 'LOW',
            'occurred_at' => '2030-06-01 09:30:00+00', 'reported_by' => $this->adminId, 'reported_at' => '2030-06-01 09:40:00+00', 'request_id' => Uuid::v4()->toRfc4122(),
            'request_hash' => str_repeat('a', 64), 'status' => 'OPEN', 'owner_id' => null, 'review_on' => '2030-06-02', 'retain_until' => '2099-01-01', 'version' => 0,
        ]);
        $this->connection->executeStatement('SET session_replication_role = DEFAULT');
        $this->accountWith('manager', ['children.manage']);
        $manager = $this->login('manager');

        $this->changeStatus($id, 'CLOSED');
        self::assertSame(409, $this->code());
        self::assertSame('Review and acknowledge outstanding incident follow-ups before closing this session.', $this->message());
        $this->changeStatus($id, 'CLOSED', token: $manager, change: ['acknowledgeIncidents' => true]);
        self::assertSame(403, $this->code(), 'the acknowledgement needs children.incidents.manage');
        self::assertSame('PLANNED', $this->connection->fetchOne('SELECT status FROM children_sessions WHERE id = ?', [$id]));

        $this->changeStatus($id, 'CLOSED', change: ['acknowledgeIncidents' => true]);
        self::assertSame(204, $this->code());
        self::assertSame(1, $this->rows('children_audit', "action = 'session.incidents.acknowledged' AND target_id = '$id'"));
        self::assertSame(1, $this->rows('children_audit', "action = 'session.status.changed' AND target_id = '$id'"));
    }

    public function testClosedIncidentsDoNotBlockClosing(): void
    {
        $id = $this->session();
        $child = $this->person('Little', $this->yearsAgo(8));
        $this->connection->executeStatement('SET session_replication_role = replica');
        $this->connection->insert('children_incidents', [
            'id' => Uuid::v4()->toRfc4122(), 'child_id' => $child, 'session_id' => $id, 'segment_id' => Uuid::v4()->toRfc4122(), 'category' => 'INJURY', 'severity' => 'LOW',
            'occurred_at' => '2030-06-01 09:30:00+00', 'reported_by' => $this->adminId, 'reported_at' => '2030-06-01 09:40:00+00', 'request_id' => Uuid::v4()->toRfc4122(),
            'request_hash' => str_repeat('a', 64), 'status' => 'CLOSED', 'owner_id' => $this->adminId, 'review_on' => '2030-06-02', 'retain_until' => '2099-01-01', 'version' => 0,
        ]);
        $this->connection->executeStatement('SET session_replication_role = DEFAULT');

        $this->changeStatus($id, 'CLOSED');

        self::assertSame(204, $this->code());
    }

    public function testStationUsersSeeOnlyTheSessionsTheyArePresentOn(): void
    {
        $mine = $this->session('+0 hours', '+2 hours');
        $notMine = $this->session('+3 hours', '+4 hours');
        $absent = $this->session('+5 hours', '+6 hours');
        $this->staff($mine, username: 'leader');
        $this->staffExisting('leader', $absent, false);
        $token = $this->login('leader');
        $window = 'from='.rawurlencode($this->at('-1 hours')).'&until='.rawurlencode($this->at('+10 hours'));

        $this->call('GET', "/api/children/station/sessions?$window", $token);
        self::assertSame(200, $this->code());
        self::assertSame([$mine], array_map(static fn (array $view): string => $view['session']['id'], $this->json()));
        self::assertSame('Africa/Johannesburg', $this->json()[0]['timeZone']);

        $this->call('GET', "/api/children/station/sessions/$mine", $token);
        self::assertSame(200, $this->code());
        self::assertSame('Juniors', $this->json()['className']);
        foreach ([$notMine, $absent, Uuid::v4()->toRfc4122()] as $other) {
            $this->call('GET', "/api/children/station/sessions/$other", $token);
            self::assertSame(403, $this->code(), 'outside the assignment, or unknown: the scope is checked before the lookup');
        }
    }

    public function testSupervisorsSeeEverySessionAndUnknownOnesAre404(): void
    {
        $first = $this->session('+0 hours', '+2 hours');
        $second = $this->session('+3 hours', '+4 hours');
        $this->accountWith('supervisor', ['children.station', 'children.supervise']);
        $token = $this->login('supervisor');
        $window = 'from='.rawurlencode($this->at('-1 hours')).'&until='.rawurlencode($this->at('+10 hours'));

        $this->call('GET', "/api/children/station/sessions?$window", $token);
        self::assertSame([$first, $second], array_map(static fn (array $view): string => $view['session']['id'], $this->json()));
        $this->call('GET', "/api/children/station/sessions/$second", $token);
        self::assertSame(200, $this->code());
        $this->call('GET', '/api/children/station/sessions/'.Uuid::v4()->toRfc4122(), $token);
        self::assertSame(404, $this->code());
    }

    public function testStationAccessEndsWhenTheWorkerIsRevokedOrWasNeverOne(): void
    {
        $id = $this->session();
        $this->staff($id, username: 'leader');
        $this->accountWith('visitor', ['children.station']);
        $window = 'from='.rawurlencode($this->at('-1 hours')).'&until='.rawurlencode($this->at('+10 hours'));

        $this->call('GET', "/api/children/station/sessions/$id", $this->login('visitor'));
        self::assertSame(403, $this->code());
        $this->call('GET', "/api/children/station/sessions?$window", $this->login('visitor'));
        self::assertSame([], $this->json());

        $leader = $this->login('leader');
        $this->connection->executeStatement("UPDATE children_workers SET revoked_at = '2026-02-01 00:00:00+00'");
        $this->call('GET', "/api/children/station/sessions/$id", $leader);
        self::assertSame(403, $this->code());
        $this->call('GET', "/api/children/station/sessions?$window", $leader);
        self::assertSame([], $this->json());
    }

    public function testStationListingValidatesItsWindowAndPaging(): void
    {
        $this->accountWith('supervisor', ['children.station', 'children.supervise']);
        $token = $this->login('supervisor');

        foreach (['limit=0', 'limit=101', 'offset=-1', 'from=nope', 'from='.rawurlencode($this->at('+0 hours')).'&until='.rawurlencode($this->at('+40 days'))] as $query) {
            $this->call('GET', "/api/children/station/sessions?$query", $token);
            self::assertSame(400, $this->code(), $query);
        }
        $this->call('GET', '/api/children/station/sessions/not-a-uuid', $token);
        self::assertSame(400, $this->code());
    }

    private function staffExisting(string $username, string $session, bool $present): void
    {
        $worker = (string) $this->connection->fetchOne('SELECT w.person_id FROM children_workers w JOIN user_accounts a ON a.id = w.account_id WHERE a.username = ?', [$username]);
        $this->connection->insert('children_session_staff', ['session_id' => $session, 'worker_id' => $worker, 'role' => 'ASSISTANT', 'present' => $present ? 'true' : 'false']);
    }
}
