<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Reporting\Presentation;

use App\Tests\ChildrenMinistry\Participation\Presentation\ChildrenApiTestCase;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Uuid;

/** Attendance reports and the ministry overview against the real schema. */
final class ReportingApiTest extends ChildrenApiTestCase
{
    private const string BASE = '/api/children';

    private string $programme;
    private string $location;
    private string $session;
    private string $child;
    private string $collector;
    private string $leader;
    private string $station;
    private string $visit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->programme = $this->programme();
        $group = $this->create('/classes', ['programmeId' => $this->programme, 'name' => 'Juniors', 'minAgeMonths' => 60, 'maxAgeMonths' => 156]);
        $this->location = $this->create('/locations', ['name' => 'Main', 'timeZone' => 'Africa/Johannesburg']);
        $room = $this->create('/rooms', ['locationId' => $this->location, 'name' => 'Room 1', 'capacity' => 12]);
        $policy = $this->create('/consent-policies', ['programmeId' => $this->programme, 'expectedVersion' => 0, 'label' => 'v1', 'documentReference' => 'docs/v1', 'contentHash' => str_repeat('a', 64)]);
        $guardian = $this->person('Parent', $this->yearsAgo(35));
        $this->collector = $this->person('Uncle', $this->yearsAgo(40));
        $this->child = $this->person('Child', $this->yearsAgo(8));
        $authority = $this->create('/guardian-authorities', ['childId' => $this->child, 'guardianId' => $guardian, 'validFrom' => $this->at('-1 day'), 'validUntil' => $this->at('+1 year'), 'evidenceReference' => 'private/guardian']);
        $this->create('/consents', ['childId' => $this->child, 'programmeId' => $this->programme, 'policyId' => $policy, 'authorityId' => $authority, 'validFrom' => $this->at('-1 hour'), 'validUntil' => $this->at('+6 months'), 'acceptedAt' => $this->at(''), 'evidenceReference' => 'private/consent']);
        $this->create('/pickup-authorizations', ['programmeId' => $this->programme, 'childId' => $this->child, 'collectorId' => $this->collector, 'authorityId' => $authority, 'validFrom' => $this->at('-1 hour'), 'validUntil' => $this->at('+6 months'), 'evidenceReference' => 'private/pickup']);
        $enrolment = $this->enrol($this->programme, $this->child);
        $this->create('/class-assignments', ['enrolmentId' => $enrolment, 'classId' => $group, 'validFrom' => $this->today('-1 day'), 'validUntil' => $this->today('+1 month')]);

        $this->session = $this->create('/sessions', ['classId' => $group, 'roomId' => $room, 'startsAt' => $this->at('-10 minutes'), 'endsAt' => $this->at('+2 hours'), 'seatLimit' => 10]);
        $this->leader = $this->person('Lead', $this->yearsAgo(30));
        $account = $this->accountWith('station', ['children.station']);
        $this->station = $this->login('station');
        $this->call('POST', '/api/children/workers', $this->admin, ['personId' => $this->leader, 'accountId' => $account, 'screenedUntil' => $this->at('+20 years'), 'trainedUntil' => $this->at('+20 years'), 'evidenceReference' => 'Checked']);
        self::assertSame(204, $this->code());
        $this->call('PUT', '/api/children/sessions/staff', $this->admin, ['sessionId' => $this->session, 'expectedVersion' => 0, 'workerId' => $this->leader, 'role' => 'LEADER', 'present' => true]);
        self::assertSame(204, $this->code());
        $this->call('PUT', '/api/children/sessions/status', $this->admin, ['sessionId' => $this->session, 'expectedVersion' => 1, 'status' => 'OPEN', 'reason' => 'Start']);
        self::assertSame(204, $this->code());
        $this->visit = $this->arrive();
    }

    private function at(string $modify): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('' === $modify ? '+0 seconds' : $modify)->format('Y-m-d\TH:i:s.u\Z');
    }

    private function arrive(): string
    {
        $this->call('POST', self::BASE.'/attendance', $this->station, ['sessionId' => $this->session, 'childId' => $this->child, 'requestId' => Uuid::v4()->toRfc4122()]);
        self::assertSame(200, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    private function collect(string $visit, int $version): void
    {
        $this->call('POST', self::BASE.'/attendance/labels', $this->station, ['attendanceId' => $visit, 'expectedVersion' => $version, 'replacementReason' => 1 === $version ? 'Replace' : null]);
        self::assertSame(200, $this->code(), $this->message());
        $code = (string) $this->json()['pickupCode'];
        $this->call('POST', self::BASE.'/attendance/checkout', $this->station, ['attendanceId' => $visit, 'expectedVersion' => $version + 1, 'requestId' => Uuid::v4()->toRfc4122(), 'collectorId' => $this->collector, 'identityVerified' => true, 'pickupCode' => $code]);
        self::assertSame(200, $this->code(), $this->message());
    }

    private function dates(): string
    {
        return 'from='.$this->today().'&until='.$this->today();
    }

    private function raw(): string
    {
        return (string) $this->client->getResponse()->getContent();
    }

    public function testReportsFilterBeforePagingAndCoverEveryMatchingVisit(): void
    {
        $this->collect($this->visit, 0);
        $this->arrive();

        $this->call('GET', self::BASE.'/attendance-reports?'.$this->dates().'&limit=1&search=child&programmeId='.$this->programme.'&locationId='.$this->location, $this->reader);
        self::assertSame(200, $this->code(), $this->message());
        $report = $this->json();
        self::assertSame([1, 2, 1, 1, 1, 'Africa/Johannesburg'], [count($report['visits']), $report['summary']['visits'], $report['summary']['children'], $report['summary']['attending'], $report['summary']['collected'], $report['timeZone']]);
        self::assertSame(['Child Mabasa', 'Juniors', 'Room 1', 'Main'], [$report['visits'][0]['childName'], $report['visits'][0]['ministryClass'], $report['visits'][0]['room'], $report['visits'][0]['location']]);
        foreach (['labelHash', 'pickupCode', 'evidenceReference', 'collectorId', 'exceptionReason'] as $secret) {
            self::assertStringNotContainsString($secret, $this->raw());
        }
        foreach ([['&search=missing', 0], ['&childId='.Uuid::v4()->toRfc4122(), 0], ['&outcome=COLLECTED', 1], ['&outcome=ATTENDING', 1], ['&outcome=TRANSFERRED', 0], ['&outcome=SUPERVISOR', 0]] as [$extra, $visits]) {
            $this->call('GET', self::BASE.'/attendance-reports?'.$this->dates().$extra, $this->admin);
            self::assertSame($visits, $this->json()['summary']['visits'], $extra);
        }
    }

    public function testExportIncludesEveryMatchingVisitAsSafeCsv(): void
    {
        $this->collect($this->visit, 0);
        $this->arrive();

        $this->call('GET', self::BASE.'/attendance-reports/export?'.$this->dates(), $this->admin);

        self::assertSame(200, $this->code());
        $response = $this->client->getResponse();
        self::assertSame('attachment; filename="children-attendance.csv"', $response->headers->get('Content-Disposition'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        self::assertCount(3, array_filter(explode("\r\n", $this->raw())), 'header and two visits');
        foreach (['Child Mabasa', 'Africa/Johannesburg', 'COLLECTED', 'ATTENDING'] as $text) {
            self::assertStringContainsString($text, $this->raw());
        }
        foreach (['pickupCode', 'evidenceReference', 'labelHash'] as $secret) {
            self::assertStringNotContainsString($secret, $this->raw());
        }
    }

    public function testDateBoundsUseChurchMidnightAndTheInclusiveLastDay(): void
    {
        $midnight = new DateTimeImmutable($this->today().' 00:00:00', new DateTimeZone('Africa/Johannesburg'));
        foreach ([[$midnight->modify('-1 second'), 0], [$midnight, 1], [$midnight->modify('+1 day -1 second'), 1], [$midnight->modify('+1 day'), 0]] as [$at, $expected]) {
            $this->connection->executeStatement('UPDATE children_attendance_segments SET arrived_at = ? WHERE attendance_id = ?', [$at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.uP'), $this->visit]);
            $this->call('GET', self::BASE.'/attendance-reports?'.$this->dates(), $this->admin);
            self::assertSame($expected, $this->json()['summary']['visits'], $at->format('c'));
        }
    }

    public function testTransfersRemainInReportsAndJourneyAcrossProgrammeAndDateFilters(): void
    {
        $nextGroup = $this->create('/classes', ['programmeId' => $this->programme, 'name' => 'Next class', 'minAgeMonths' => 60, 'maxAgeMonths' => 156]);
        $nextRoom = $this->create('/rooms', ['locationId' => $this->location, 'name' => 'Room B', 'capacity' => 10]);
        $next = $this->create('/sessions', ['classId' => $nextGroup, 'roomId' => $nextRoom, 'startsAt' => $this->at('-5 minutes'), 'endsAt' => $this->at('+90 minutes'), 'seatLimit' => 10]);
        $second = $this->person('Second', $this->yearsAgo(30));
        $this->call('POST', self::BASE.'/workers', $this->admin, ['personId' => $second, 'accountId' => $this->accountWith('second', ['children.station']), 'screenedUntil' => $this->at('+20 years'), 'trainedUntil' => $this->at('+20 years'), 'evidenceReference' => 'Checked']);
        self::assertSame(204, $this->code());
        $this->call('PUT', self::BASE.'/sessions/staff', $this->admin, ['sessionId' => $next, 'expectedVersion' => 0, 'workerId' => $second, 'role' => 'LEADER', 'present' => true]);
        self::assertSame(204, $this->code());
        $this->call('PUT', self::BASE.'/sessions/status', $this->admin, ['sessionId' => $next, 'expectedVersion' => 1, 'status' => 'OPEN', 'reason' => 'Start']);
        self::assertSame(204, $this->code());
        $this->call('POST', self::BASE.'/class-assignments/end', $this->admin, ['assignmentId' => (string) $this->connection->fetchOne('SELECT id FROM children_class_assignments'), 'expectedVersion' => 0]);
        self::assertSame(204, $this->code());
        $this->call('POST', self::BASE.'/class-assignments', $this->admin, ['enrolmentId' => (string) $this->connection->fetchOne('SELECT id FROM children_enrolments'), 'classId' => $nextGroup, 'validFrom' => $this->today('-1 day'), 'validUntil' => $this->today('+1 month')]);
        self::assertSame(201, $this->code(), $this->message());

        $this->call('POST', self::BASE.'/attendance/transfers', $this->admin, ['attendanceId' => $this->visit, 'expectedVersion' => 0, 'targetSessionId' => $next, 'reason' => 'Move class']);
        self::assertSame(200, $this->code(), $this->message());
        $this->call('POST', self::BASE.'/attendance/labels', $this->admin, ['attendanceId' => $this->visit, 'expectedVersion' => 1, 'replacementReason' => 'After transfer']);
        self::assertSame(200, $this->code(), $this->message());
        $code = (string) $this->json()['pickupCode'];
        $this->call('POST', self::BASE.'/attendance/checkout', $this->admin, ['attendanceId' => $this->visit, 'expectedVersion' => 2, 'requestId' => Uuid::v4()->toRfc4122(), 'collectorId' => $this->collector, 'identityVerified' => true, 'pickupCode' => $code]);
        self::assertSame(200, $this->code(), $this->message());

        $this->call('GET', self::BASE.'/attendance-reports?'.$this->dates().'&childId='.$this->child, $this->admin);
        self::assertSame([1, 1], [$this->json()['summary']['transferred'], $this->json()['summary']['collected']]);

        $this->call('GET', self::BASE."/attendance/{$this->visit}/timeline?limit=1", $this->admin);
        self::assertSame(200, $this->code(), $this->message());
        self::assertSame([2, $this->session], [$this->json()['summary']['visits'], $this->json()['visits'][0]['sessionId']]);
        $this->call('GET', self::BASE."/attendance/{$this->visit}/timeline?limit=1&offset=1", $this->admin);
        self::assertSame($next, $this->json()['visits'][0]['sessionId']);
        $this->call('GET', self::BASE."/attendance/{$this->visit}/timeline?limit=1&offset=1", $this->admin);
        self::assertStringContainsString('Room B', $this->raw());
        self::assertStringContainsString('COLLECTED', $this->raw());
    }

    public function testSupervisorCollectionsAreDistinguishedWithoutTheirReason(): void
    {
        $this->call('POST', self::BASE.'/attendance/supervisor-checkout', $this->admin, ['attendanceId' => $this->visit, 'expectedVersion' => 0, 'requestId' => Uuid::v4()->toRfc4122(), 'collectorId' => $this->collector, 'identityVerified' => true, 'reason' => 'Private handover reason']);
        self::assertSame(200, $this->code(), $this->message());

        $this->call('GET', self::BASE.'/attendance-reports?'.$this->dates().'&outcome=SUPERVISOR', $this->admin);

        self::assertSame([1, true], [$this->json()['summary']['supervisorCollections'], $this->json()['visits'][0]['supervisorCollection']]);
        self::assertStringNotContainsString('Private handover reason', $this->raw());
    }

    public function testTheTimelineIsTheWholeJourneyAndPagedWithCompleteTotals(): void
    {
        $this->call('GET', self::BASE."/attendance/{$this->visit}/timeline", $this->reader);
        self::assertSame(200, $this->code(), $this->message());
        self::assertSame([1, 1, $this->session], [$this->json()['summary']['visits'], count($this->json()['visits']), $this->json()['visits'][0]['sessionId']]);
        $this->call('GET', self::BASE.'/attendance/'.Uuid::v4()->toRfc4122().'/timeline', $this->admin);
        self::assertSame(404, $this->code());
        $this->call('GET', self::BASE."/attendance/{$this->visit}/timeline?limit=101", $this->admin);
        self::assertSame(400, $this->code());
    }

    public function testAChildsVisitsAreListedNewestFirstForReaders(): void
    {
        $this->collect($this->visit, 0);
        $second = $this->arrive();

        $this->call('GET', self::BASE."/attendance/children/{$this->child}", $this->reader);

        self::assertSame(200, $this->code(), $this->message());
        self::assertSame([$second, $this->visit], array_column($this->json(), 'id'));
        $this->call('GET', self::BASE."/attendance/children/{$this->child}?limit=1&offset=1", $this->reader);
        self::assertSame([$this->visit], array_column($this->json(), 'id'));
        $this->call('GET', self::BASE.'/attendance/children/nope', $this->reader);
        self::assertSame(400, $this->code());
        $this->call('GET', self::BASE."/attendance/children/{$this->child}", $this->station);
        self::assertSame(403, $this->code());
    }

    public function testReportPermissionsAndBoundsAreEnforced(): void
    {
        $this->call('GET', self::BASE.'/attendance-reports?'.$this->dates());
        self::assertSame(401, $this->code());
        foreach (['&limit=101', '&outcome=INVALID', '&search='.str_repeat('x', 121), '&programmeId=nope'] as $extra) {
            $this->call('GET', self::BASE.'/attendance-reports?'.$this->dates().$extra, $this->admin);
            self::assertSame(400, $this->code(), $extra);
        }
        $this->call('GET', self::BASE.'/attendance-reports?from='.$this->today().'&until='.$this->today('-1 day'), $this->admin);
        self::assertSame(400, $this->code());
        $this->call('GET', self::BASE.'/attendance-reports?from='.$this->today('-366 days').'&until='.$this->today(), $this->admin);
        self::assertSame(400, $this->code(), '367 days');
        $this->call('GET', self::BASE.'/attendance-reports?from='.$this->today('-365 days').'&until='.$this->today(), $this->admin);
        self::assertSame(200, $this->code(), '366 days inclusive');
        $this->call('GET', self::BASE.'/attendance-reports?from='.$this->today(), $this->admin);
        self::assertSame(400, $this->code(), 'both dates are required');
        $this->call('GET', self::BASE.'/attendance-reports', $this->station);
        self::assertSame(403, $this->code());
        $this->call('GET', self::BASE.'/attendance-reports/export?'.$this->dates(), $this->reader);
        self::assertSame(403, $this->code(), 'exporting also needs manage');
    }

    public function testTheOverviewIsReadOnlyAndShowsEverySectionToAnAdministrator(): void
    {
        $audits = $this->rows('children_audit');

        $this->call('GET', self::BASE.'/overview', $this->admin);

        self::assertSame(200, $this->code(), $this->message());
        $overview = $this->json();
        self::assertSame('Africa/Johannesburg', $overview['timeZone']);
        foreach (['upcoming', 'teams', 'safeguarding', 'incidents', 'communications'] as $section) {
            self::assertIsArray($overview[$section], $section);
        }
        self::assertSame([0, 0, 0, 0], [$overview['incidents']['total'], $overview['communications']['total'], $overview['safeguarding']['total'], count($overview['teams']['sessions'])]);
        foreach (['description', 'immediateAction', 'restrictedNotes', 'allergies'] as $secret) {
            self::assertStringNotContainsString($secret, $this->raw());
        }
        self::assertSame($audits, $this->rows('children_audit'));
    }

    public function testTheOverviewSectionsMatchTheirOwnEndpoints(): void
    {
        $this->call('POST', self::BASE.'/incidents', $this->admin, [
            'requestId' => Uuid::v4()->toRfc4122(),
            'childId' => $this->child,
            'sessionId' => $this->session,
            'segmentId' => (string) $this->connection->fetchOne('SELECT id FROM children_attendance_segments LIMIT 1'),
            'category' => 'INJURY',
            'severity' => 'LOW',
            'occurredAt' => $this->at(''),
            'description' => 'Private injury detail',
            'immediateAction' => 'Private response',
            'reviewOn' => $this->today(),
            'retainUntil' => $this->today('+1 year'),
        ]);
        self::assertSame(201, $this->code(), $this->message());
        $this->call('GET', self::BASE.'/incidents?limit=5', $this->admin);
        $incidents = $this->json();
        $this->call('GET', self::BASE.'/safeguarding-follow-up?days=30&limit=5', $this->admin);
        $followUp = $this->json();
        $this->call('GET', self::BASE.'/email-deliveries?limit=5', $this->admin);
        $deliveries = $this->json();

        $this->call('GET', self::BASE.'/overview', $this->admin);

        $overview = $this->json();
        self::assertSame(array_column($incidents['incidents'], 'incident'), $overview['incidents']['incidents']);
        self::assertSame([$incidents['total'], $incidents['overdue'], $incidents['unassigned']], [$overview['incidents']['total'], $overview['incidents']['overdue'], $overview['incidents']['unassigned']]);
        self::assertSame($followUp['summary'], $overview['safeguarding']['summary']);
        self::assertSame($followUp['issues'], $overview['safeguarding']['issues']);
        self::assertSame($deliveries['total'], $overview['communications']['total']);
        self::assertStringNotContainsString('Private injury detail', $this->raw());
    }

    public function testTheOverviewShowsReadOnlyStaffNoRestrictedSections(): void
    {
        $this->call('GET', self::BASE.'/overview', $this->reader);

        self::assertSame(200, $this->code(), $this->message());
        self::assertIsArray($this->json()['upcoming']);
        foreach (['teams', 'safeguarding', 'incidents', 'communications'] as $section) {
            self::assertNull($this->json()[$section], $section);
        }
    }

    public function testTheOverviewIsOpenToSafeguardingAndIncidentManagersEvenWithoutRead(): void
    {
        $this->accountWith('safeguarder', ['children.safeguarding']);
        $this->accountWith('incidentmanager', ['children.incidents.manage']);

        $this->call('GET', self::BASE.'/overview', $this->login('safeguarder'));
        self::assertSame(200, $this->code(), $this->message());
        self::assertNull($this->json()['upcoming']);
        self::assertIsArray($this->json()['safeguarding']);
        self::assertIsArray($this->json()['communications']);
        self::assertNull($this->json()['incidents']);

        $this->call('GET', self::BASE.'/overview', $this->login('incidentmanager'));
        self::assertSame(200, $this->code(), $this->message());
        self::assertIsArray($this->json()['incidents']);
        self::assertNull($this->json()['safeguarding']);

        $this->call('GET', self::BASE.'/overview', $this->station);
        self::assertSame(403, $this->code());
        $this->call('GET', self::BASE.'/overview');
        self::assertSame(401, $this->code());
    }

    public function testUpcomingSessionsCoverSevenChurchDaysAndExcludeFinishedOnes(): void
    {
        $soon = new DateTimeImmutable($this->today('+2 days').' 09:00', new DateTimeZone('Africa/Johannesburg'));
        $far = new DateTimeImmutable($this->today('+9 days').' 09:00', new DateTimeZone('Africa/Johannesburg'));
        $class = (string) $this->connection->fetchOne('SELECT class_id FROM children_sessions LIMIT 1');
        $room = $this->create('/rooms', ['locationId' => $this->location, 'name' => 'Room 2', 'capacity' => 8]);
        $otherClass = $this->create('/classes', ['programmeId' => $this->programme, 'name' => 'Seniors', 'minAgeMonths' => 60, 'maxAgeMonths' => 156]);
        $utc = new DateTimeZone('UTC');
        $near = $this->create('/sessions', ['classId' => $otherClass, 'roomId' => $room, 'startsAt' => $soon->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'), 'endsAt' => $soon->modify('+1 hour')->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'), 'seatLimit' => 2]);
        $this->create('/sessions', ['classId' => $otherClass, 'roomId' => $room, 'startsAt' => $far->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'), 'endsAt' => $far->modify('+1 hour')->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'), 'seatLimit' => 2]);
        self::assertNotSame('', $class);

        $this->call('GET', self::BASE.'/overview', $this->reader);

        self::assertSame([1, $near, 'Seniors', 'Room 2', 'PLANNED'], [$this->json()['upcoming']['total'], $this->json()['upcoming']['sessions'][0]['id'], $this->json()['upcoming']['sessions'][0]['ministryClass'], $this->json()['upcoming']['sessions'][0]['room'], $this->json()['upcoming']['sessions'][0]['status']]);
    }
}
