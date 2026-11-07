<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Incidents\Presentation;

use App\Tests\ChildrenMinistry\Participation\Presentation\ChildrenApiTestCase;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Uuid;

/** Restricted incidents against the real schema: metadata, private details, follow-up and retention. */
final class IncidentApiTest extends ChildrenApiTestCase
{
    private const string BASE = '/api/children/incidents';

    private string $programme;
    private string $group;
    private string $room;
    private string $session;
    private string $child;
    private string $guardian;
    private string $authority;
    private string $collector;
    private string $stationToken;
    private string $leader;
    private string $segment;
    private string $visit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->programme = $this->programme();
        $this->group = $this->create('/classes', ['programmeId' => $this->programme, 'name' => 'Juniors', 'minAgeMonths' => 60, 'maxAgeMonths' => 156]);
        $location = $this->create('/locations', ['name' => 'Main', 'timeZone' => 'Africa/Johannesburg']);
        $this->room = $this->create('/rooms', ['locationId' => $location, 'name' => 'Room 1', 'capacity' => 12]);
        $policy = $this->create('/consent-policies', ['programmeId' => $this->programme, 'expectedVersion' => 0, 'label' => 'v1', 'documentReference' => 'docs/v1', 'contentHash' => str_repeat('a', 64)]);
        $this->guardian = $this->person('Parent', $this->yearsAgo(35));
        $this->collector = $this->person('Uncle', $this->yearsAgo(40));
        $this->child = $this->person('Child', $this->yearsAgo(8));
        $this->authority = $this->create('/guardian-authorities', ['childId' => $this->child, 'guardianId' => $this->guardian, 'validFrom' => $this->at('-1 day'), 'validUntil' => $this->at('+1 year'), 'evidenceReference' => 'private/guardian']);
        $this->create('/consents', ['childId' => $this->child, 'programmeId' => $this->programme, 'policyId' => $policy, 'authorityId' => $this->authority, 'validFrom' => $this->at('-1 hour'), 'validUntil' => $this->at('+6 months'), 'acceptedAt' => $this->at(''), 'evidenceReference' => 'private/consent']);
        $this->create('/pickup-authorizations', ['programmeId' => $this->programme, 'childId' => $this->child, 'collectorId' => $this->collector, 'authorityId' => $this->authority, 'validFrom' => $this->at('-1 hour'), 'validUntil' => $this->at('+6 months'), 'evidenceReference' => 'private/pickup']);
        $enrolment = $this->enrol($this->programme, $this->child);
        $this->create('/class-assignments', ['enrolmentId' => $enrolment, 'classId' => $this->group, 'validFrom' => $this->today('-1 day'), 'validUntil' => $this->today('+1 month')]);

        $this->session = $this->create('/sessions', ['classId' => $this->group, 'roomId' => $this->room, 'startsAt' => $this->at('-10 minutes'), 'endsAt' => $this->at('+2 hours'), 'seatLimit' => 10]);
        $this->leader = $this->person('Lead', $this->yearsAgo(30));
        $station = $this->accountWith('station', ['children.station', 'children.incidents.record']);
        $this->stationToken = $this->login('station');
        $this->call('POST', '/api/children/workers', $this->admin, ['personId' => $this->leader, 'accountId' => $station, 'screenedUntil' => $this->at('+20 years'), 'trainedUntil' => $this->at('+20 years'), 'evidenceReference' => 'Checked']);
        self::assertSame(204, $this->code());
        $this->staff($this->leader, true);
        $this->call('PUT', '/api/children/sessions/status', $this->admin, ['sessionId' => $this->session, 'expectedVersion' => 1, 'status' => 'OPEN', 'reason' => 'Start']);
        self::assertSame(204, $this->code());

        $this->call('POST', '/api/children/attendance', $this->stationToken, ['sessionId' => $this->session, 'childId' => $this->child, 'requestId' => Uuid::v4()->toRfc4122()]);
        self::assertSame(200, $this->code(), $this->message());
        $this->visit = (string) $this->json()['id'];
        $this->segment = (string) $this->connection->fetchOne('SELECT id FROM children_attendance_segments WHERE attendance_id = ?', [$this->visit]);
    }

    private function at(string $modify): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('' === $modify ? '+0 seconds' : $modify)->format('Y-m-d\TH:i:s.u\Z');
    }

    private function staff(string $worker, bool $present): void
    {
        $current = (int) $this->connection->fetchOne('SELECT version FROM children_sessions WHERE id = ?', [$this->session]);
        $this->call('PUT', '/api/children/sessions/staff', $this->admin, ['sessionId' => $this->session, 'expectedVersion' => $current, 'workerId' => $worker, 'role' => 'LEADER', 'present' => $present]);
        self::assertSame(204, $this->code());
    }

    /** @return array<string, mixed> */
    private function report(): array
    {
        return [
            'requestId' => Uuid::v4()->toRfc4122(),
            'childId' => $this->child,
            'sessionId' => $this->session,
            'segmentId' => $this->segment,
            'category' => 'INJURY',
            'severity' => 'LOW',
            'occurredAt' => $this->at(''),
            'description' => 'Private injury detail',
            'immediateAction' => 'Private immediate response',
            'reviewOn' => $this->today(),
            'retainUntil' => $this->today('+1 year'),
        ];
    }

    /** @param array<string, mixed> $body */
    private function file(array $body, ?string $token = null): string
    {
        $this->call('POST', self::BASE, $token ?? $this->admin, $body);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    /** @param array<string, mixed> $body */
    private function followUp(string $id, array $body, ?string $token = null): void
    {
        $this->call('POST', self::BASE."/$id/follow-up", $token ?? $this->admin, $body);
    }

    private function raw(): string
    {
        return (string) $this->client->getResponse()->getContent();
    }

    public function testReportRetriesAreSafeMetadataIsPrivateAndEveryDetailsPageIsAudited(): void
    {
        $request = $this->report();
        $id = $this->file($request);
        $this->call('POST', self::BASE, $this->admin, $request);
        self::assertSame([201, $id], [$this->code(), $this->json()['id']]);
        self::assertSame(1, $this->rows('children_incidents'));
        self::assertSame(1, $this->rows('children_audit', "action = 'incident.reported' AND target_id = '$id'"));
        $audits = $this->rows('children_audit');

        $this->call('GET', self::BASE.'?limit=1', $this->admin);
        self::assertSame(200, $this->code(), $this->message());
        $list = $this->json();
        self::assertSame([1, 1, 1, 'Child Mabasa'], [$list['total'], $list['overdue'], $list['unassigned'], $list['incidents'][0]['childName']]);
        self::assertSame(['INJURY', 'LOW', 'OPEN', null, 0], [$list['incidents'][0]['incident']['category'], $list['incidents'][0]['incident']['severity'], $list['incidents'][0]['incident']['status'], $list['incidents'][0]['incident']['ownerId'], $list['incidents'][0]['incident']['version']]);
        foreach (['Private injury', 'Private immediate', 'description', 'immediateAction', 'note'] as $secret) {
            self::assertStringNotContainsString($secret, $this->raw());
        }
        self::assertSame($audits, $this->rows('children_audit'), 'listing metadata is not an access to private details');

        $this->call('POST', self::BASE."/$id/access", $this->admin, ['limit' => 1, 'offset' => 0]);
        self::assertSame(200, $this->code(), $this->message());
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertSame(['Private injury detail', 'Private immediate response', 'REPORTED', 1], [$this->json()['details']['description'], $this->json()['details']['immediateAction'], $this->json()['events'][0]['type'], $this->json()['totalEvents']]);
        $this->call('POST', self::BASE."/$id/access", $this->admin, ['limit' => 1, 'offset' => 1]);
        self::assertSame(200, $this->code());
        self::assertSame([], $this->json()['events']);
        self::assertSame(2, $this->rows('children_audit', "action = 'incident.accessed' AND actor_id = '{$this->adminId}' AND target_id = '$id'"));

        $request['description'] = 'Changed payload';
        $this->call('POST', self::BASE, $this->admin, $request);
        self::assertSame(409, $this->code());
        self::assertSame(0, $this->rows('children_audit', "reason LIKE '%Private%'"), 'no private text reaches the ordinary audit log');
    }

    public function testTheIncidentLifecycleKeepsHistoryAndRequiresOwnershipAndCurrentGuardianContact(): void
    {
        $id = $this->file($this->report());

        $this->followUp($id, ['expectedVersion' => 0, 'action' => 'CLOSED', 'note' => 'Resolved']);
        self::assertSame(409, $this->code(), 'closing needs an owner');
        $this->followUp($id, ['expectedVersion' => 0, 'action' => 'TRIAGED', 'ownerId' => $this->adminId, 'reviewOn' => $this->today('+2 days'), 'note' => 'Private owner triage']);
        self::assertSame(204, $this->code());
        $this->call('GET', self::BASE.'/owners', $this->admin);
        self::assertSame(200, $this->code());
        self::assertContains($this->adminId, array_column($this->json(), 'id'));
        $this->call('GET', self::BASE."/$id/guardians", $this->admin);
        self::assertSame([['authorityId' => $this->authority, 'guardianId' => $this->guardian, 'name' => 'Parent Mabasa']], $this->json());
        self::assertStringNotContainsString('evidenceReference', $this->raw());

        $this->followUp($id, ['expectedVersion' => 1, 'action' => 'GUARDIAN_CONTACT', 'guardianAuthorityId' => $this->authority, 'note' => 'Private guardian conversation']);
        self::assertSame(204, $this->code());
        $this->followUp($id, ['expectedVersion' => 2, 'action' => 'CLOSED', 'note' => 'Private resolution']);
        self::assertSame(204, $this->code());
        $this->call('GET', self::BASE, $this->admin);
        self::assertSame(0, $this->json()['total'], 'the default view shows active incidents only');
        $this->call('GET', self::BASE.'?status=CLOSED', $this->admin);
        self::assertSame(1, $this->json()['total']);
        $this->followUp($id, ['expectedVersion' => 3, 'action' => 'ACTION', 'note' => 'Cannot edit closed']);
        self::assertSame(409, $this->code());
        $this->followUp($id, ['expectedVersion' => 3, 'action' => 'REOPENED', 'note' => 'New information received']);
        self::assertSame(204, $this->code());

        self::assertSame(5, $this->rows('children_incident_events', "incident_id = '$id'"));
        self::assertSame([0, 1, 2, 3, 4], array_map(intval(...), $this->connection->fetchFirstColumn('SELECT revision FROM children_incident_events WHERE incident_id = ? ORDER BY revision', [$id])));
        $this->call('POST', self::BASE."/$id/access", $this->admin, ['limit' => 25, 'offset' => 0]);
        foreach (['Private guardian conversation', 'Private resolution', 'New information received'] as $note) {
            self::assertStringContainsString($note, $this->raw());
        }
        self::assertSame($this->guardian, $this->json()['events'][2]['guardianId']);
        self::assertSame([1, 1, 1, 1, 1], [
            $this->rows('children_audit', "action = 'incident.followup.triaged'"),
            $this->rows('children_audit', "action = 'incident.followup.guardian_contact'"),
            $this->rows('children_audit', "action = 'incident.followup.closed'"),
            $this->rows('children_audit', "action = 'incident.followup.reopened'"),
            $this->rows('children_audit', "action = 'incident.reported'"),
        ]);
        self::assertSame(0, $this->rows('children_audit', "action LIKE 'incident.%' AND reason <> ''"), 'incident audit rows carry no notes or details');
    }

    public function testAStaleFollowUpCannotLosePrivateHistory(): void
    {
        $id = $this->file($this->report());
        $body = ['expectedVersion' => 0, 'action' => 'TRIAGED', 'ownerId' => $this->adminId, 'reviewOn' => $this->today('+1 day'), 'note' => 'Owner review'];

        $this->followUp($id, $body);
        self::assertSame(204, $this->code());
        $this->followUp($id, $body);
        self::assertSame(409, $this->code());
        self::assertSame(2, $this->rows('children_incident_events', "incident_id = '$id'"));
    }

    public function testReportingScopeAndPrivatePermissionsAreIndependent(): void
    {
        $request = $this->report();
        $id = $this->file($request, $this->stationToken);
        self::assertSame($this->leader, (string) $this->connection->fetchOne('SELECT person_id FROM children_workers LIMIT 1'));
        self::assertSame(1, $this->rows('children_incidents', "id = '$id'"));

        $this->call('GET', self::BASE, $this->stationToken);
        self::assertSame(403, $this->code(), 'recording does not allow reading');
        $this->call('POST', self::BASE."/$id/access", $this->stationToken, ['limit' => 25, 'offset' => 0]);
        self::assertSame(403, $this->code());
        $this->call('GET', self::BASE.'/owners', $this->stationToken);
        self::assertSame(403, $this->code());
        $this->call('GET', self::BASE."/$id/guardians", $this->stationToken);
        self::assertSame(403, $this->code());
        $this->call('POST', self::BASE.'/purge-expired', $this->stationToken, ['limit' => 1]);
        self::assertSame(403, $this->code());
        $this->call('GET', self::BASE.'/attendance?sessionId='.$this->session, $this->stationToken);
        self::assertSame(200, $this->code());
        self::assertSame(['Child Mabasa', $this->segment], [$this->json()['visits'][0]['childName'], $this->json()['visits'][0]['visit']['segmentId']]);

        $this->staff($this->leader, false);
        $request['requestId'] = Uuid::v4()->toRfc4122();
        $this->call('POST', self::BASE, $this->stationToken, $request);
        self::assertSame(403, $this->code(), 'staff who are not present on the session cannot report on it');
        $this->call('GET', self::BASE.'/attendance?sessionId='.$this->session, $this->stationToken);
        self::assertSame(403, $this->code());

        $this->call('GET', self::BASE, $this->reader);
        self::assertSame(403, $this->code());
        $this->call('POST', self::BASE, $this->reader, $request);
        self::assertSame(403, $this->code(), 'children.read alone cannot report');
        $this->call('POST', self::BASE, $this->admin, $request);
        self::assertSame(201, $this->code(), 'managers report on any session');
    }

    public function testInvalidAttendanceTimesAndOwnersDoNotCreateOrChangeReports(): void
    {
        $request = $this->report();
        $request['occurredAt'] = $this->at('-1 hour');
        $this->call('POST', self::BASE, $this->admin, $request);
        self::assertSame(400, $this->code(), 'before the child arrived');
        $request['occurredAt'] = $this->at('+1 hour');
        $this->call('POST', self::BASE, $this->admin, $request);
        self::assertSame(400, $this->code(), 'in the future');
        $request['occurredAt'] = $this->at('');
        $request['segmentId'] = Uuid::v4()->toRfc4122();
        $this->call('POST', self::BASE, $this->admin, $request);
        self::assertSame(400, $this->code(), 'a segment that is not the child’s');
        $request['segmentId'] = $this->segment;
        $request['reviewOn'] = $this->today('-1 day');
        $this->call('POST', self::BASE, $this->admin, $request);
        self::assertSame(400, $this->code());
        self::assertSame(0, $this->rows('children_incidents'));

        $request = $this->report();
        $id = $this->file($request);
        $plain = $this->accountWith('incidentreader', ['children.read']);
        $this->followUp($id, ['expectedVersion' => 0, 'action' => 'TRIAGED', 'ownerId' => $plain, 'reviewOn' => $this->today(), 'note' => 'Invalid owner']);
        self::assertSame(400, $this->code());
        $this->followUp($id, ['expectedVersion' => 0, 'action' => 'TRIAGED', 'ownerId' => $this->adminId, 'reviewOn' => $this->today('-1 day'), 'note' => 'Past review']);
        self::assertSame(400, $this->code());
        $this->followUp($id, ['expectedVersion' => 0, 'action' => 'GUARDIAN_CONTACT', 'note' => 'No authority']);
        self::assertSame(400, $this->code());
        $this->followUp($id, ['expectedVersion' => 0, 'action' => 'GUARDIAN_CONTACT', 'guardianAuthorityId' => Uuid::v4()->toRfc4122(), 'note' => 'Unknown authority']);
        self::assertSame(400, $this->code());
        self::assertSame(1, $this->rows('children_incident_events'), 'refused follow-up leaves the history alone');
        $this->call('POST', self::BASE."/$id/access", $this->admin, ['limit' => 101, 'offset' => 0]);
        self::assertSame(400, $this->code());
    }

    public function testGuardianContactNeedsAuthorityOfThisChildThatIsCurrent(): void
    {
        $id = $this->file($this->report());
        $other = $this->person('Other', $this->yearsAgo(9));
        $foreign = $this->create('/guardian-authorities', ['childId' => $other, 'guardianId' => $this->guardian, 'validFrom' => $this->at('-1 day'), 'validUntil' => $this->at('+1 year'), 'evidenceReference' => 'private/other']);

        $this->followUp($id, ['expectedVersion' => 0, 'action' => 'GUARDIAN_CONTACT', 'guardianAuthorityId' => $foreign, 'note' => 'Wrong child']);
        self::assertSame(400, $this->code());
        $this->connection->executeStatement('UPDATE children_guardian_authorities SET revoked_at = now() WHERE id = ?', [$this->authority]);
        $this->followUp($id, ['expectedVersion' => 0, 'action' => 'GUARDIAN_CONTACT', 'guardianAuthorityId' => $this->authority, 'note' => 'Revoked']);
        self::assertSame(400, $this->code());
        $this->call('GET', self::BASE."/$id/guardians", $this->admin);
        self::assertSame([], $this->json());
    }

    public function testMalformedRequestsAreRejectedAndUnknownIncidentsAreNotFound(): void
    {
        $good = $this->report();
        foreach ([
            ['category' => 'WEATHER'],
            ['severity' => 'SEVERE'],
            ['category' => ''],
            ['description' => ''],
            ['description' => str_repeat('x', 5001)],
            ['immediateAction' => str_repeat('x', 3001)],
            ['requestId' => 'nope'],
            ['childId' => null],
            ['occurredAt' => 'yesterday'],
            ['reviewOn' => 'soon'],
            ['retainUntil' => null],
        ] as $change) {
            $this->call('POST', self::BASE, $this->admin, $change + $good);
            self::assertSame(400, $this->code(), json_encode($change, JSON_THROW_ON_ERROR));
        }
        self::assertSame(0, $this->rows('children_incidents'));
        $missing = Uuid::v4()->toRfc4122();
        $this->call('POST', self::BASE."/$missing/access", $this->admin, ['limit' => 25, 'offset' => 0]);
        self::assertSame(404, $this->code());
        $this->followUp($missing, ['expectedVersion' => 0, 'action' => 'ACTION', 'note' => 'Nothing']);
        self::assertSame(404, $this->code());
        $this->call('GET', self::BASE."/$missing/guardians", $this->admin);
        self::assertSame(404, $this->code());
        $this->followUp($missing, ['expectedVersion' => 0, 'action' => 'DELETE', 'note' => 'Nothing']);
        self::assertSame(400, $this->code());
        $this->followUp($missing, ['expectedVersion' => 0, 'action' => 'REPORTED', 'note' => 'Nothing']);
        self::assertSame(400, $this->code());
        $this->followUp($missing, ['action' => 'ACTION', 'note' => 'Nothing']);
        self::assertSame(400, $this->code(), 'a missing version is not zero');
        $this->call('GET', self::BASE.'?status=EVERYTHING', $this->admin);
        self::assertSame(400, $this->code());
        $this->call('GET', self::BASE.'?limit=101', $this->admin);
        self::assertSame(400, $this->code());
        $this->call('GET', self::BASE.'/attendance', $this->admin);
        self::assertSame(400, $this->code());
        $this->call('POST', self::BASE.'/purge-expired', $this->admin, ['limit' => 0]);
        self::assertSame(400, $this->code());
        $this->call('POST', self::BASE.'/purge-expired', $this->admin, ['limit' => 101]);
        self::assertSame(400, $this->code());
        foreach ([['GET', self::BASE], ['POST', self::BASE], ['GET', self::BASE.'/owners'], ['POST', self::BASE.'/purge-expired'], ['GET', self::BASE.'/attendance?sessionId='.$this->session]] as [$method, $uri]) {
            $this->call($method, $uri, null, []);
            self::assertSame(401, $this->code(), "$method $uri");
        }
    }

    public function testListFiltersAndTheOverdueTotalsIgnoreThem(): void
    {
        $first = $this->file($this->report());
        $later = $this->report();
        $later['reviewOn'] = $this->today('+5 days');
        $second = $this->file($later);
        $this->followUp($first, ['expectedVersion' => 0, 'action' => 'TRIAGED', 'ownerId' => $this->adminId, 'reviewOn' => $this->today(), 'note' => 'Mine']);

        $this->call('GET', self::BASE.'?overdueOnly=true', $this->admin);
        self::assertSame([1, 2, 1], [$this->json()['total'], $this->json()['overdue'] + 1, $this->json()['unassigned']], 'only the one due today is listed; totals ignore the filter');
        self::assertSame($first, $this->json()['incidents'][0]['incident']['id']);
        $this->call('GET', self::BASE.'?status=OPEN', $this->admin);
        self::assertSame([$second], array_column(array_column($this->json()['incidents'], 'incident'), 'id'));
        $this->call('GET', self::BASE.'?status=IN_REVIEW&sessionId='.$this->session.'&childId='.$this->child, $this->admin);
        self::assertSame([$first], array_column(array_column($this->json()['incidents'], 'incident'), 'id'));
        $this->call('GET', self::BASE.'?childId='.Uuid::v4()->toRfc4122(), $this->admin);
        self::assertSame(0, $this->json()['total']);
        $this->call('GET', self::BASE.'?status=ALL&limit=1&offset=1', $this->admin);
        self::assertSame([2, 1], [$this->json()['total'], count($this->json()['incidents'])]);
    }

    public function testOpenFollowUpBlocksClosingASessionUntilAcknowledgedAndSurvivesIt(): void
    {
        $id = $this->file($this->report());
        $this->call('POST', '/api/children/attendance/labels', $this->stationToken, ['attendanceId' => $this->visit, 'expectedVersion' => 0, 'replacementReason' => null]);
        self::assertSame(200, $this->code(), $this->message());
        $code = (string) $this->json()['pickupCode'];
        $this->call('POST', '/api/children/attendance/checkout', $this->stationToken, ['attendanceId' => $this->visit, 'expectedVersion' => 1, 'requestId' => Uuid::v4()->toRfc4122(), 'collectorId' => $this->collector, 'identityVerified' => true, 'pickupCode' => $code]);
        self::assertSame(200, $this->code(), $this->message());

        $this->call('GET', "/api/children/sessions/{$this->session}/closeout", $this->admin);
        self::assertSame(1, $this->json()['incidents']['unresolved']);
        $version = (int) $this->connection->fetchOne('SELECT version FROM children_sessions WHERE id = ?', [$this->session]);
        $this->call('PUT', '/api/children/sessions/status', $this->admin, ['sessionId' => $this->session, 'expectedVersion' => $version, 'status' => 'CLOSED', 'reason' => 'Room checked']);
        self::assertSame(409, $this->code());
        $this->call('PUT', '/api/children/sessions/status', $this->admin, ['sessionId' => $this->session, 'expectedVersion' => $version, 'status' => 'CLOSED', 'reason' => 'Room checked', 'acknowledgeIncidents' => true]);
        self::assertSame(204, $this->code());

        $this->call('GET', self::BASE.'?sessionId='.$this->session, $this->admin);
        self::assertSame([$id, 'OPEN'], [$this->json()['incidents'][0]['incident']['id'], $this->json()['incidents'][0]['incident']['status']]);
    }

    public function testRetentionIsEnforcedWithoutReadSidePurges(): void
    {
        $id = $this->file($this->report());
        $this->followUp($id, ['expectedVersion' => 0, 'action' => 'TRIAGED', 'ownerId' => $this->adminId, 'reviewOn' => $this->today(), 'note' => 'Private owner triage']);
        $this->connection->executeStatement('UPDATE children_incidents SET review_on = ?, retain_until = ? WHERE id = ?', [$this->today('-2 days'), $this->today('-1 day'), $id]);

        $this->call('GET', self::BASE, $this->admin);
        self::assertSame(0, $this->json()['total']);
        $this->call('POST', self::BASE."/$id/access", $this->admin, ['limit' => 25, 'offset' => 0]);
        self::assertSame(404, $this->code());
        $this->call('GET', self::BASE."/$id/guardians", $this->admin);
        self::assertSame(404, $this->code());
        $this->followUp($id, ['expectedVersion' => 1, 'action' => 'ACTION', 'note' => 'Too late']);
        self::assertSame(404, $this->code());
        self::assertSame(1, $this->rows('children_incident_details'), 'reads never purge');

        $this->call('POST', self::BASE.'/purge-expired', $this->admin, ['limit' => 100]);
        self::assertSame([200, 1], [$this->code(), $this->json()['purged']]);
        self::assertSame(0, $this->rows('children_incident_details'));
        self::assertSame(1, $this->rows('children_incidents'));
        self::assertSame(['', ''], array_map(strval(...), $this->connection->fetchFirstColumn('SELECT note FROM children_incident_events ORDER BY revision')));
        self::assertSame(1, $this->rows('children_audit', "action = 'incident.details.purged' AND reason = '1'"));
        $this->call('POST', self::BASE.'/purge-expired', $this->admin, ['limit' => 100]);
        self::assertSame(0, $this->json()['purged'], 'nothing left to purge');
    }

    public function testAttendanceLookupSearchesByNameBeforePaging(): void
    {
        $this->call('GET', self::BASE.'/attendance?sessionId='.$this->session.'&search=Child', $this->admin);
        self::assertSame(1, $this->json()['total']);
        $this->call('GET', self::BASE.'/attendance?sessionId='.$this->session.'&search=Nobody', $this->admin);
        self::assertSame([0, []], [$this->json()['total'], $this->json()['visits']]);
        $this->call('GET', self::BASE.'/attendance?sessionId='.$this->session.'&limit=1&offset=1', $this->admin);
        self::assertSame([1, []], [$this->json()['total'], $this->json()['visits']]);
    }
}
