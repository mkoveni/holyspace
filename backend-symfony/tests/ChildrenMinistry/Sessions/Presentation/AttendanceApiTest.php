<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Sessions\Presentation;

use App\Tests\ChildrenMinistry\Participation\Presentation\ChildrenApiTestCase;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Uuid;

/** The whole day of a session against the real schema: admission, labels, handover, transfer, closeout. */
final class AttendanceApiTest extends ChildrenApiTestCase
{
    private string $programme;
    private string $group;
    private string $room;
    private string $session;
    private string $child;
    private string $guardian;
    private string $collector;
    private string $stationToken;
    private string $stationAccount;
    private string $leader;

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
        $this->child = $this->childWith($policy, 'Child');

        $this->session = $this->makeSession('-10 minutes', '+2 hours');
        $this->leader = $this->person('Lead', $this->yearsAgo(30));
        $this->stationAccount = $this->accountWith('station', ['children.station']);
        $this->stationToken = $this->login('station');
        $this->call('POST', '/api/children/workers', $this->admin, ['personId' => $this->leader, 'accountId' => $this->stationAccount, 'screenedUntil' => $this->at('+20 years'), 'trainedUntil' => $this->at('+20 years'), 'evidenceReference' => 'Checked']);
        self::assertSame(204, $this->code());
        $this->staff($this->session);
        $this->call('PUT', '/api/children/sessions/status', $this->admin, ['sessionId' => $this->session, 'expectedVersion' => 1, 'status' => 'OPEN', 'reason' => 'Start']);
        self::assertSame(204, $this->code());
    }

    private function at(string $modify): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('' === $modify ? '+0 seconds' : $modify)->format('Y-m-d\TH:i:s.u\Z');
    }

    /** A child who is enrolled, placed, covered by guardian authority and consent, and collectable by the collector. */
    private function childWith(string $policy, string $name): string
    {
        $child = $this->person($name, $this->yearsAgo(8));
        $authority = $this->create('/guardian-authorities', ['childId' => $child, 'guardianId' => $this->guardian, 'validFrom' => $this->at('-1 day'), 'validUntil' => $this->at('+1 year'), 'evidenceReference' => 'private/guardian']);
        $this->create('/consents', ['childId' => $child, 'programmeId' => $this->programme, 'policyId' => $policy, 'authorityId' => $authority, 'validFrom' => $this->at('-1 hour'), 'validUntil' => $this->at('+6 months'), 'acceptedAt' => $this->at(''), 'evidenceReference' => 'private/consent']);
        $this->create('/pickup-authorizations', ['programmeId' => $this->programme, 'childId' => $child, 'collectorId' => $this->collector, 'authorityId' => $authority, 'validFrom' => $this->at('-1 hour'), 'validUntil' => $this->at('+6 months'), 'evidenceReference' => 'private/pickup']);
        $enrolment = $this->enrol($this->programme, $child);
        $this->create('/class-assignments', ['enrolmentId' => $enrolment, 'classId' => $this->group, 'validFrom' => $this->today('-1 day'), 'validUntil' => $this->today('+1 month')]);

        return $child;
    }

    private function makeSession(string $start, string $end, ?string $room = null, ?string $group = null): string
    {
        return $this->create('/sessions', ['classId' => $group ?? $this->group, 'roomId' => $room ?? $this->room, 'startsAt' => $this->at($start), 'endsAt' => $this->at($end), 'seatLimit' => 10]);
    }

    private function staff(string $session, ?string $worker = null): void
    {
        $current = (int) $this->connection->fetchOne('SELECT version FROM children_sessions WHERE id = ?', [$session]);
        $this->call('PUT', '/api/children/sessions/staff', $this->admin, ['sessionId' => $session, 'expectedVersion' => $current, 'workerId' => $worker ?? $this->leader, 'role' => 'LEADER', 'present' => true]);
        self::assertSame(204, $this->code());
    }

    /** @return array<mixed> */
    private function checkIn(?string $child = null, ?string $session = null, ?string $request = null, ?string $token = null): array
    {
        $this->call('POST', '/api/children/attendance', $token ?? $this->stationToken, ['sessionId' => $session ?? $this->session, 'childId' => $child ?? $this->child, 'requestId' => $request ?? Uuid::v4()->toRfc4122()]);

        return $this->json();
    }

    /** @return array<mixed> */
    private function label(string $visit, int $version, ?string $reason = null): array
    {
        $this->call('POST', '/api/children/attendance/labels', $this->stationToken, ['attendanceId' => $visit, 'expectedVersion' => $version, 'replacementReason' => $reason]);
        self::assertSame(200, $this->code(), $this->message());

        return $this->json();
    }

    /** @param array<string, mixed> $extra */
    private function checkOut(string $visit, int $version, ?string $code, array $extra = []): void
    {
        $this->call('POST', '/api/children/attendance/checkout', $this->stationToken, $extra + ['attendanceId' => $visit, 'expectedVersion' => $version, 'requestId' => Uuid::v4()->toRfc4122(), 'collectorId' => $this->collector, 'identityVerified' => true, 'pickupCode' => $code]);
    }

    public function testCheckInRecordsTheVisitItsSegmentAndEventAndIsIdempotent(): void
    {
        $request = Uuid::v4()->toRfc4122();
        $visit = $this->checkIn(request: $request);

        self::assertSame(200, $this->code(), $this->message());
        self::assertSame([$this->child, $this->session, 0, false, null, false], [$visit['childId'], $visit['sessionId'], $visit['version'], $visit['labelIssued'], $visit['checkedOutAt'], $visit['exceptionUsed']]);
        $id = $visit['id'];
        self::assertSame(1, $this->rows('children_attendance_segments', "attendance_id = '$id' AND session_id = '{$this->session}' AND left_at IS NULL"));
        self::assertSame(1, $this->rows('children_attendance_events', "attendance_id = '$id' AND type = 'ARRIVED' AND actor_id = '{$this->stationAccount}'"));
        self::assertSame(1, $this->rows('children_audit', "action = 'child.checked.in' AND target_id = '$id'"));

        $again = $this->checkIn(request: $request);
        self::assertSame([200, $id], [$this->code(), $again['id']]);
        self::assertSame(1, $this->rows('children_attendance'));

        $other = $this->childWith($this->connection->fetchOne('SELECT current_policy_id FROM children_programmes WHERE id = ?', [$this->programme]), 'Second');
        $this->checkIn($other, request: $request);
        self::assertSame(409, $this->code());
        self::assertSame('Arrival request ID was already used for another arrival.', $this->message());
    }

    public function testAChildHasOneOpenVisitAtATime(): void
    {
        $this->checkIn();
        self::assertSame(200, $this->code());
        $second = $this->makeSession('+3 hours', '+5 hours');

        $this->checkIn(session: $this->makeSession('-5 minutes', '+90 minutes', $this->create('/rooms', ['locationId' => $this->connection->fetchOne('SELECT location_id FROM children_rooms WHERE id = ?', [$this->room]), 'name' => 'Room 2', 'capacity' => 12]), $this->create('/classes', ['programmeId' => $this->programme, 'name' => 'Seniors', 'minAgeMonths' => 60, 'maxAgeMonths' => 156])));
        self::assertSame(403, $this->code(), 'the leader is not on that session');
        $this->checkIn(request: Uuid::v4()->toRfc4122());
        self::assertSame(409, $this->code(), 'the unique open-visit index (or the capacity rule) refuses a second check-in');
        self::assertSame(1, $this->rows('children_attendance'));
        self::assertNotSame('', $second);
    }

    public function testAdmissionRulesAreAppliedUnderLock(): void
    {
        $tooOld = $this->person('Teenager', $this->yearsAgo(15));
        $this->checkIn($tooOld);
        self::assertSame(400, $this->code());
        self::assertSame("Child is outside this class's age range.", $this->message());

        $unplaced = $this->person('Unplaced', $this->yearsAgo(8));
        $this->checkIn($unplaced);
        self::assertSame(409, $this->code());
        self::assertSame('Active programme enrolment is required for the session date.', $this->message());

        $this->connection->executeStatement('UPDATE children_consents SET revoked_at = now(), revocation_reason = ?', ['Withdrawn']);
        $this->checkIn();
        self::assertSame(409, $this->code());
        self::assertSame('Current guardian consent for the programme policy must cover the session.', $this->message());
        self::assertSame(0, $this->rows('children_attendance'));
    }

    public function testArrivalsAreRefusedBeforeTheWindowAndAfterTheEnd(): void
    {
        $early = $this->makeSession('+3 hours', '+4 hours', $this->create('/rooms', ['locationId' => $this->connection->fetchOne('SELECT location_id FROM children_rooms WHERE id = ?', [$this->room]), 'name' => 'Room 2', 'capacity' => 12]), $this->create('/classes', ['programmeId' => $this->programme, 'name' => 'Seniors', 'minAgeMonths' => 60, 'maxAgeMonths' => 156]));
        $this->staff($early);
        $this->call('PUT', '/api/children/sessions/status', $this->admin, ['sessionId' => $early, 'expectedVersion' => 1, 'status' => 'OPEN', 'reason' => 'Start']);

        $this->checkIn(session: $early);

        self::assertSame(409, $this->code());
        self::assertSame('Arrivals require an open session, from 30 minutes before its start until its end.', $this->message());
    }

    public function testFullHandoverWithALabelAndCodeLeavesAnAuditTrail(): void
    {
        $visit = $this->checkIn();
        $id = $visit['id'];
        $label = $this->label($id, 0);
        self::assertSame([1, 1, $id], [$label['labelVersion'], $label['attendanceVersion'], $label['attendanceId']]);
        self::assertSame(22, strlen($label['pickupCode']));
        self::assertSame(hash('sha256', $label['pickupCode']), $this->connection->fetchOne('SELECT label_hash FROM children_attendance WHERE id = ?', [$id]));

        $this->call('GET', "/api/children/attendance/$id/collectors", $this->stationToken);
        self::assertSame(200, $this->code());
        self::assertSame([[$this->collector, 'Uncle Mabasa']], array_map(static fn (array $row): array => [$row['collectorId'], $row['name']], $this->json()));
        self::assertArrayNotHasKey('evidenceReference', $this->json()[0]);

        $this->checkOut($id, 1, $label['pickupCode']);
        self::assertSame(200, $this->code(), $this->message());
        $done = $this->json();
        self::assertSame([$this->collector, true, 2, false], [$done['collectorId'], $done['identityVerified'], $done['version'], $done['exceptionUsed']]);
        self::assertNotNull($done['checkedOutAt']);
        self::assertNull($this->connection->fetchOne('SELECT label_hash FROM children_attendance WHERE id = ?', [$id]));
        self::assertSame(0, $this->rows('children_attendance_segments', "attendance_id = '$id' AND left_at IS NULL"));
        self::assertSame(1, $this->rows('children_audit', "action = 'attendance.collected' AND target_id = '$id'"));

        $this->call('GET', "/api/children/attendance/$id/events", $this->admin);
        self::assertSame(['ARRIVED', 'LABEL_ISSUED', 'COLLECTED'], array_column($this->json(), 'type'));
        $this->call('GET', "/api/children/attendance/$id/segments", $this->reader);
        self::assertSame(1, count($this->json()));
        self::assertNotNull($this->json()[0]['leftAt']);
    }

    public function testWrongCodesAreCountedAndCommittedAndTheFifthLocksHandover(): void
    {
        $id = $this->checkIn()['id'];
        $label = $this->label($id, 0);
        $version = 1;

        for ($i = 1; $i <= 4; ++$i) {
            $this->checkOut($id, $version, 'wrong');
            self::assertSame(400, $this->code(), "attempt $i");
            self::assertSame('Pickup code is invalid or expired. Reload attendance before retrying.', $this->message());
            ++$version;
            self::assertSame($i, (int) $this->connection->fetchOne('SELECT failed_attempts FROM children_attendance WHERE id = ?', [$id]), 'the failure survived the refusal');
        }
        $this->checkOut($id, $version, 'wrong');
        self::assertSame(429, $this->code());
        ++$version;
        $this->checkOut($id, $version, $label['pickupCode']);
        self::assertSame(429, $this->code(), 'even the right code is refused while locked');
        self::assertSame(5, (int) $this->connection->fetchOne('SELECT failed_attempts FROM children_attendance WHERE id = ?', [$id]));
        self::assertSame(5, $this->rows('children_audit', "action = 'pickup.label.rejected' AND target_id = '$id'"));

        $this->connection->executeStatement("UPDATE children_attendance SET attempts_reset_at = now() - interval '1 minute' WHERE id = ?", [$id]);
        $this->checkOut($id, $version, $label['pickupCode']);
        self::assertSame(200, $this->code(), $this->message());
    }

    public function testHandoverStillNeedsAnAdultCollectorWithAGrantAndConfirmedIdentity(): void
    {
        $id = $this->checkIn()['id'];
        $label = $this->label($id, 0);
        $this->checkOut($id, 1, $label['pickupCode'], ['identityVerified' => false]);
        self::assertSame([400, 'Confirm collector identity before handover.'], [$this->code(), $this->message()]);
        $stranger = $this->person('Stranger', $this->yearsAgo(30));
        $this->checkOut($id, 1, $label['pickupCode'], ['collectorId' => $stranger]);
        self::assertSame([409, 'A current pickup authorization and guardian authority are required.'], [$this->code(), $this->message()]);
        $this->connection->executeStatement('UPDATE children_pickup_authorizations SET revoked_at = now(), revocation_reason = ?', ['Revoked']);
        $this->checkOut($id, 1, $label['pickupCode']);
        self::assertSame(409, $this->code());
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT failed_attempts FROM children_attendance WHERE id = ?', [$id]));
    }

    public function testASupervisorCanReleaseWithoutACodeButOnlyWithAReason(): void
    {
        $id = $this->checkIn()['id'];
        $this->accountWith('supervisor', ['children.supervise']);
        $supervisor = $this->login('supervisor');
        $body = ['attendanceId' => $id, 'expectedVersion' => 0, 'requestId' => Uuid::v4()->toRfc4122(), 'collectorId' => $this->collector, 'identityVerified' => true];

        $this->call('POST', '/api/children/attendance/supervisor-checkout', $this->stationToken, $body + ['reason' => 'Phone dead']);
        self::assertSame(403, $this->code(), 'station staff cannot use the exception route');
        $this->call('POST', '/api/children/attendance/supervisor-checkout', $supervisor, $body + ['reason' => ' ']);
        self::assertSame(400, $this->code());
        $this->call('POST', '/api/children/attendance/supervisor-checkout', $this->admin, $body + ['reason' => 'Phone dead; parent known to staff']);

        self::assertSame(200, $this->code(), $this->message());
        self::assertTrue($this->json()['exceptionUsed']);
        self::assertSame(1, $this->rows('children_attendance_events', "attendance_id = '$id' AND type = 'SUPERVISOR_COLLECTION' AND reason = 'Phone dead; parent known to staff'"));
        self::assertSame(1, $this->rows('children_audit', "action = 'attendance.supervisor_collection' AND target_id = '$id'"));
    }

    public function testTransferMovesTheVisitToAnotherSessionAndKeepsOneOpenSegment(): void
    {
        $id = $this->checkIn()['id'];
        $this->label($id, 0);
        $room = $this->create('/rooms', ['locationId' => $this->connection->fetchOne('SELECT location_id FROM children_rooms WHERE id = ?', [$this->room]), 'name' => 'Room 2', 'capacity' => 12]);
        $group = $this->create('/classes', ['programmeId' => $this->programme, 'name' => 'Seniors', 'minAgeMonths' => 60, 'maxAgeMonths' => 156]);
        $target = $this->makeSession('-5 minutes', '+90 minutes', $room, $group);
        $second = $this->person('Second', $this->yearsAgo(30));
        $this->call('POST', '/api/children/workers', $this->admin, ['personId' => $second, 'accountId' => $this->accountWith('second', ['children.station']), 'screenedUntil' => $this->at('+20 years'), 'trainedUntil' => $this->at('+20 years'), 'evidenceReference' => 'Checked']);
        $this->staff($target, $second);
        $this->call('POST', '/api/children/class-assignments/end', $this->admin, ['assignmentId' => (string) $this->connection->fetchOne('SELECT id FROM children_class_assignments'), 'expectedVersion' => 0]);
        $enrolment = (string) $this->connection->fetchOne('SELECT id FROM children_enrolments');
        $this->call('POST', '/api/children/class-assignments', $this->admin, ['enrolmentId' => $enrolment, 'classId' => $group, 'validFrom' => $this->today('-1 day'), 'validUntil' => $this->today('+1 month')]);
        $this->call('PUT', '/api/children/sessions/status', $this->admin, ['sessionId' => $target, 'expectedVersion' => 1, 'status' => 'OPEN', 'reason' => 'Start']);
        self::assertSame(204, $this->code());

        $this->call('POST', '/api/children/attendance/transfers', $this->stationToken, ['attendanceId' => $id, 'expectedVersion' => 1, 'targetSessionId' => $target, 'reason' => 'Moved up']);
        self::assertSame(403, $this->code(), 'transfers need children.supervise');
        $this->call('POST', '/api/children/attendance/transfers', $this->admin, ['attendanceId' => $id, 'expectedVersion' => 1, 'targetSessionId' => $target, 'reason' => 'Moved up']);

        self::assertSame(200, $this->code(), $this->message());
        self::assertSame([$target, 2, false], [$this->json()['sessionId'], $this->json()['version'], $this->json()['labelIssued']]);
        self::assertSame(2, $this->rows('children_attendance_segments', "attendance_id = '$id'"));
        self::assertSame(1, $this->rows('children_attendance_segments', "attendance_id = '$id' AND left_at IS NULL AND session_id = '$target'"));
        self::assertSame(1, $this->rows('children_audit', "action = 'child.transferred' AND target_id = '$id' AND reason = 'Moved up'"));

        $this->call('GET', "/api/children/sessions/$target/closeout", $this->admin);
        self::assertSame(200, $this->code());
        self::assertSame(1, $this->json()['awaitingCollection']);
        $this->call('GET', "/api/children/sessions/{$this->session}/closeout", $this->admin);
        self::assertSame([1, 1, 0, 0, 1], [$this->json()['visits'], $this->json()['children'], $this->json()['awaitingCollection'], $this->json()['collected'], $this->json()['transferredOut']]);
    }

    public function testARevokedGrantStopsHandoverEvenForASupervisor(): void
    {
        $id = $this->checkIn()['id'];
        $code = (string) $this->label($id, 0)['pickupCode'];
        $grant = (string) $this->connection->fetchOne('SELECT id FROM children_pickup_authorizations');
        $this->call('POST', '/api/children/pickup-authorizations/revoke', $this->admin, ['id' => $grant, 'expectedVersion' => 0, 'reason' => 'Parent instruction']);
        self::assertSame(204, $this->code());

        $this->checkOut($id, 1, $code);
        self::assertSame(409, $this->code());
        $this->call('POST', '/api/children/attendance/supervisor-checkout', $this->admin, ['attendanceId' => $id, 'expectedVersion' => 1, 'requestId' => Uuid::v4()->toRfc4122(), 'collectorId' => $this->collector, 'identityVerified' => true, 'reason' => 'Lost label']);
        self::assertSame(409, $this->code());
    }

    public function testRevokingTheGuardianAuthorityEndsAdmissionAndSomeoneUnrelatedCannotCollect(): void
    {
        $id = $this->checkIn()['id'];
        $code = (string) $this->label($id, 0)['pickupCode'];
        $unrelated = $this->person('Other adult', $this->yearsAgo(30));
        $this->checkOut($id, 1, $code, ['collectorId' => $unrelated]);
        self::assertSame(409, $this->code(), 'relationship or household is not a pickup right');

        $second = $this->childWith((string) $this->connection->fetchOne('SELECT current_policy_id FROM children_programmes WHERE id = ?', [$this->programme]), 'Second');
        $authority = (string) $this->connection->fetchOne('SELECT id FROM children_guardian_authorities WHERE child_id = ?', [$second]);
        $this->call('POST', '/api/children/guardian-authorities/revoke', $this->admin, ['id' => $authority, 'expectedVersion' => 0, 'reason' => 'Authority ended']);
        self::assertSame(204, $this->code());
        $this->checkIn($second);
        self::assertSame(409, $this->code(), 'derived consent no longer holds');
    }

    public function testACollectorNeedsAKnownAdultBirthDate(): void
    {
        $unknown = $this->person('Unknown collector', null);
        $authority = (string) $this->connection->fetchOne('SELECT id FROM children_guardian_authorities');
        $this->call('POST', '/api/children/pickup-authorizations', $this->admin, ['programmeId' => $this->programme, 'childId' => $this->child, 'collectorId' => $unknown, 'authorityId' => $authority, 'validFrom' => $this->at('+1 hour'), 'validUntil' => $this->at('+2 hours'), 'evidenceReference' => 'signed/request']);
        self::assertSame(400, $this->code());
    }

    public function testStationViewsShowOnlyWhatStaffNeed(): void
    {
        $id = $this->checkIn()['id'];
        $this->label($id, 0);

        $this->call('GET', "/api/children/station/sessions/{$this->session}/attendance", $this->stationToken);
        self::assertSame(200, $this->code());
        self::assertSame([['Child Mabasa', true]], array_map(static fn (array $row): array => [$row['childName'], $row['attendance']['labelIssued']], $this->json()));
        self::assertArrayNotHasKey('labelHash', $this->json()[0]['attendance']);
        $this->call('GET', "/api/children/sessions/{$this->session}/roster", $this->stationToken);
        self::assertSame([$id], array_column($this->json(), 'id'));
        $this->call('GET', "/api/children/attendance/$id", $this->stationToken);
        self::assertSame(200, $this->code());

        $this->call('GET', "/api/children/station/sessions/{$this->session}/day?view=ATTENDING", $this->stationToken);
        $row = $this->json()['children'][0];
        self::assertSame(['Child Mabasa', true, []], [$row['name'], $row['ready'], $row['issues']]);
        self::assertSame(1, $this->json()['total']);

        $this->call('GET', "/api/children/station/sessions/{$this->session}/day?view=ATTENDING&search=zzzz", $this->stationToken);
        self::assertSame([[], 0], [$this->json()['children'], $this->json()['total']]);
        $this->call('GET', "/api/children/station/sessions/{$this->session}/day?view=SIDEWAYS", $this->stationToken);
        self::assertSame(400, $this->code());
    }

    public function testTheExpectedViewListsPlacedChildrenWithPreparationIssues(): void
    {
        $unconsented = $this->person('Kid', $this->yearsAgo(8));
        $enrolment = $this->enrol($this->programme, $unconsented);
        $this->create('/class-assignments', ['enrolmentId' => $enrolment, 'classId' => $this->group, 'validFrom' => $this->today('-1 day'), 'validUntil' => $this->today('+1 month')]);

        $this->call('GET', "/api/children/station/sessions/{$this->session}/day?view=EXPECTED", $this->stationToken);
        self::assertSame(200, $this->code(), $this->message());
        $byChild = [];
        foreach ($this->json()['children'] as $row) {
            $byChild[$row['childId']] = $row;
        }
        self::assertSame([true, []], [$byChild[$this->child]['ready'], $byChild[$this->child]['issues']]);
        self::assertSame([false, ['Guardian consent for the current policy must cover this session through its end.']], [$byChild[$unconsented]['ready'], $byChild[$unconsented]['issues']]);
        self::assertSame(2, $this->json()['total']);

        $this->call('GET', "/api/children/sessions/{$this->session}/expected-children", $this->stationToken);
        self::assertSame(200, $this->code());
        self::assertCount(2, $this->json());
        self::assertSame(['childId', 'name', 'enrolmentId', 'assignmentId'], array_keys($this->json()[0]));
    }

    public function testCloseoutCountsWithoutLeakingCodesAndShowsIncidentsOnlyToThoseWhoManageThem(): void
    {
        $id = $this->checkIn()['id'];
        $this->call('GET', "/api/children/sessions/{$this->session}/closeout", $this->admin);
        self::assertSame(200, $this->code());
        $closeout = $this->json();
        self::assertSame([1, 1, 1, 0, 0, 1, 0, 0], [$closeout['visits'], $closeout['children'], $closeout['awaitingCollection'], $closeout['collected'], $closeout['transferredOut'], $closeout['labelsNeedingAttention'], $closeout['failedHandoverAttempts'], $closeout['supervisorCollections']]);
        self::assertSame(['unresolved' => 0, 'overdue' => 0, 'unassigned' => 0], $closeout['incidents']);

        $this->accountWith('manager', ['children.manage']);
        $this->call('GET', "/api/children/sessions/{$this->session}/closeout", $this->login('manager'));
        self::assertNull($this->json()['incidents']);
        $this->call('GET', "/api/children/sessions/{$this->session}/closeout", $this->reader);
        self::assertSame(403, $this->code());
        self::assertNotSame('', $id);
    }

    public function testEveryRouteNeedsItsPermissionAndAnAssignedSession(): void
    {
        $id = $this->checkIn()['id'];
        $this->accountWith('outsider', ['children.station', 'children.read']);
        $outsider = $this->login('outsider');
        $stationRoutes = [
            ['GET', "/api/children/attendance/$id"],
            ['GET', "/api/children/attendance/$id/collectors"],
            ['GET', "/api/children/sessions/{$this->session}/roster"],
            ['GET', "/api/children/sessions/{$this->session}/expected-children"],
            ['GET', "/api/children/station/sessions/{$this->session}/attendance"],
            ['GET', "/api/children/station/sessions/{$this->session}/day"],
        ];
        foreach ($stationRoutes as [$method, $path]) {
            $this->call($method, $path, null);
            self::assertSame(401, $this->code(), $path);
            $this->call($method, $path, $this->reader);
            self::assertSame(403, $this->code(), "reader may not $path");
            $this->call($method, $path, $outsider);
            self::assertSame(403, $this->code(), "unassigned station user may not $path");
        }
        $this->call('GET', "/api/children/attendance/$id/events", $this->stationToken);
        self::assertSame(403, $this->code());
        $this->call('GET', "/api/children/attendance/$id/segments", $this->stationToken);
        self::assertSame(403, $this->code(), 'segments need children.read, which station staff do not hold');
    }

    public function testSupportRecordsOpenFromASessionOnlyForPresentStaffAndThatSessionsChildren(): void
    {
        $this->call('PUT', '/api/children/support', $this->admin, ['childId' => $this->child, 'expectedVersion' => 0, 'allergies' => 'Peanuts', 'careInstructions' => 'Inhaler in bag', 'restrictedNotes' => 'None', 'reviewOn' => $this->today('+3 months'), 'retainUntil' => $this->today('+1 year')]);
        self::assertSame(204, $this->code());
        $elsewhere = $this->person('Elsewhere', $this->yearsAgo(8));
        $this->call('PUT', '/api/children/support', $this->admin, ['childId' => $elsewhere, 'expectedVersion' => 0, 'allergies' => 'Dust', 'careInstructions' => 'None', 'restrictedNotes' => 'None', 'reviewOn' => $this->today('+3 months'), 'retainUntil' => $this->today('+1 year')]);

        $this->connection->executeStatement("INSERT INTO role_permissions (role_id, permission_id) SELECT ur.role_id, p.id FROM user_roles ur, permissions p WHERE ur.user_id = ? AND p.code = 'children.support'", [$this->stationAccount]);
        $this->accountWith('outsider', ['children.support', 'children.station']);
        $outsider = $this->login('outsider');
        $station = $this->login('station');

        $this->call('POST', '/api/children/support/access', $station, ['childId' => $this->child, 'sessionId' => $this->session]);
        self::assertSame(200, $this->code(), $this->message());
        self::assertSame('Peanuts', $this->json()['allergies']);
        self::assertSame(1, $this->rows('children_audit', "action = 'support.accessed' AND target_id = '{$this->child}' AND actor_id = '{$this->stationAccount}'"));

        $this->call('POST', '/api/children/support/access', $station, ['childId' => $elsewhere, 'sessionId' => $this->session]);
        self::assertSame(403, $this->code(), 'a child neither placed in the class nor checked in');
        $this->call('POST', '/api/children/support/access', $outsider, ['childId' => $this->child, 'sessionId' => $this->session]);
        self::assertSame(403, $this->code(), 'staff not present on the session');
        $this->call('POST', '/api/children/support/access', $station, ['childId' => $this->child]);
        self::assertSame(403, $this->code(), 'without a session only managers may open it');
        self::assertSame(1, $this->rows('children_audit', "action = 'support.accessed'"), 'refusals leave no access record');
    }
}
