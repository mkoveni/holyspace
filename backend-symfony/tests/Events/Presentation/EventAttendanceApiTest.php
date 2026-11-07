<?php

declare(strict_types=1);

namespace App\Tests\Events\Presentation;

use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final class EventAttendanceApiTest extends EventsApiTestCase
{
    private string $occurrence;
    private string $door;
    private string $attendanceReader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountWith('door', ['events.attendance.manage', 'events.attendance.read']);
        $this->accountWith('attreader', ['events.attendance.read']);
        $this->door = $this->login('door');
        $this->attendanceReader = $this->login('attreader');
        $this->event($this->draft((new DateTimeImmutable('+2 days'))->format('Y-m-d'), 1));
        $this->occurrence = (string) $this->connection->fetchOne('SELECT id FROM event_occurrences LIMIT 1');
        $this->connection->executeStatement("UPDATE event_registration_policies SET open = TRUE WHERE occurrence_id = '{$this->occurrence}'");
    }

    /** Moves the occurrence so that "now" falls inside its admission window. */
    private function startsIn(string $interval): void
    {
        $this->connection->executeStatement("UPDATE event_occurrences SET starts_at = now() + interval '$interval', ends_at = now() + interval '$interval' + interval '90 minutes' WHERE id = ?", [$this->occurrence]);
    }

    private function booking(string $name): string
    {
        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/registrations", $this->registrar, ['requestId' => Uuid::v4()->toRfc4122(), 'personId' => $this->person($name)]);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    private function checkIn(string $registration, int $version = 0, ?string $request = null): void
    {
        $this->call('POST', "/api/events/registrations/$registration/check-in", $this->door, ['requestId' => $request ?? Uuid::v4()->toRfc4122(), 'expectedVersion' => $version]);
    }

    public function testCheckInRecordsArrivalAndIsIdempotent(): void
    {
        $registration = $this->booking('Ada');
        $this->startsIn('1 hour');
        $request = Uuid::v4()->toRfc4122();

        $this->checkIn($registration, request: $request);
        self::assertSame(201, $this->code(), $this->message());
        $attendance = $this->json()['id'];
        $this->checkIn($registration, request: $request);
        self::assertSame($attendance, $this->json()['id']);

        self::assertSame(1, $this->rows('event_attendance', "id = '$attendance' AND status = 'PRESENT'"));
        self::assertSame(1, $this->rows('event_attendance_history', "action = 'CHECKED_IN' AND attendance_id = '$attendance'"));
        $this->checkIn($registration);
        self::assertSame(409, $this->code(), 'a second check-in needs the correction action');
        self::assertSame(1, $this->rows('event_attendance'));
    }

    public function testCheckInIsRefusedOutsideTheWindowOrForCancelledBookings(): void
    {
        $registration = $this->booking('Ada');
        $this->checkIn($registration);
        self::assertSame(409, $this->code(), 'more than two hours before the start');

        $this->startsIn('1 hour');
        $this->checkIn($registration, 4);
        self::assertSame(409, $this->code(), 'stale booking version');
        $this->call('POST', "/api/events/registrations/$registration/cancel", $this->registrar, ['expectedVersion' => 0, 'reason' => 'Away']);
        $this->checkIn($registration, 1);
        self::assertSame(409, $this->code(), 'cancelled booking');
        $this->checkIn(Uuid::v4()->toRfc4122());
        self::assertSame(404, $this->code());
        self::assertSame(0, $this->rows('event_attendance'));
    }

    public function testWalkInsAreBookedAndAdmittedOrWaitlisted(): void
    {
        $this->startsIn('30 minutes');
        $ada = $this->person('Ada');
        $request = Uuid::v4()->toRfc4122();

        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/walk-ins", $this->door, ['requestId' => $request, 'personId' => $ada]);
        self::assertSame(201, $this->code(), $this->message());
        self::assertTrue($this->json()['admitted']);
        self::assertSame('CONFIRMED', $this->json()['status']);
        $registration = $this->json()['registrationId'];
        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/walk-ins", $this->door, ['requestId' => $request, 'personId' => $ada]);
        self::assertSame($registration, $this->json()['registrationId']);
        self::assertSame(1, $this->rows('event_attendance'));

        $this->connection->executeStatement("UPDATE event_registration_policies SET capacity = 1 WHERE occurrence_id = '{$this->occurrence}'");
        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/walk-ins", $this->door, ['requestId' => Uuid::v4()->toRfc4122(), 'personId' => $this->person('Ben')]);
        self::assertFalse($this->json()['admitted']);
        self::assertSame('WAITLISTED', $this->json()['status']);
        self::assertSame(1, $this->rows('event_attendance'));
    }

    public function testCorrectionsVoidAndReinstateWithHistoryAndStaleVersionsConflict(): void
    {
        $registration = $this->booking('Ada');
        $this->startsIn('1 hour');
        $this->checkIn($registration);
        $url = "/api/events/registrations/$registration/attendance-correction";

        $this->call('POST', $url, $this->door, ['expectedVersion' => 5, 'status' => 'VOIDED', 'reason' => 'x']);
        self::assertSame(409, $this->code());
        $this->call('POST', $url, $this->door, ['expectedVersion' => 0, 'status' => 'GONE', 'reason' => 'x']);
        self::assertSame(400, $this->code());
        $this->call('POST', $url, $this->door, ['expectedVersion' => 0, 'status' => 'VOIDED', 'reason' => ' ']);
        self::assertSame(400, $this->code());
        $this->call('POST', $url, $this->door, ['expectedVersion' => 0, 'status' => 'VOIDED', 'reason' => 'Wrong person']);
        self::assertSame(204, $this->code());
        $this->call('POST', $url, $this->door, ['expectedVersion' => 1, 'status' => 'PRESENT', 'reason' => 'Right person']);
        self::assertSame(204, $this->code());

        $this->call('GET', "/api/events/registrations/$registration/attendance-history", $this->attendanceReader);
        self::assertSame(['REINSTATED', 'VOIDED', 'CHECKED_IN'], array_column($this->json(), 'action'));
        self::assertSame('Right person', $this->json()[0]['reason']);
        $this->call('POST', '/api/events/registrations/'.Uuid::v4()->toRfc4122().'/attendance-correction', $this->door, ['expectedVersion' => 0, 'status' => 'VOIDED', 'reason' => 'x']);
        self::assertSame(404, $this->code());
    }

    public function testThePresentPersonCannotHaveTheirBookingCancelled(): void
    {
        $registration = $this->booking('Ada');
        $this->startsIn('1 hour');
        $this->checkIn($registration);

        $this->call('POST', "/api/events/registrations/$registration/cancel", $this->registrar, ['expectedVersion' => 0, 'reason' => 'x']);

        self::assertSame(409, $this->code());
    }

    public function testTheRosterShowsStatesCountsAndSearchesByName(): void
    {
        $present = $this->booking('Ada');
        $this->booking('Ben');
        $cancelled = $this->booking('Cara');
        $this->call('POST', "/api/events/registrations/$cancelled/cancel", $this->registrar, ['expectedVersion' => 0, 'reason' => 'x']);
        $this->startsIn('1 hour');
        $this->checkIn($present);

        $this->call('GET', "/api/events/occurrences/{$this->occurrence}/attendance", $this->attendanceReader);
        self::assertSame(200, $this->code(), $this->message());
        self::assertSame(['PRESENT', 'EXPECTED', 'CANCELLED'], array_column($this->json()['attendees'], 'status'));
        self::assertSame(['confirmed' => 2, 'waitlisted' => 0, 'cancelled' => 1, 'attended' => 1, 'absent' => 0], $this->json()['counts']);
        self::assertTrue($this->json()['admissionOpen']);
        self::assertSame('Ada Mabasa', $this->json()['attendees'][0]['name']);
        self::assertSame('PRESENT', $this->json()['attendees'][0]['attendance']['status']);
        self::assertNull($this->json()['attendees'][1]['attendance']);

        $this->call('GET', "/api/events/occurrences/{$this->occurrence}/attendance?search=ben", $this->attendanceReader);
        self::assertSame(1, $this->json()['total']);
        $this->call('GET', "/api/events/occurrences/{$this->occurrence}/attendance?search=nobody", $this->attendanceReader);
        self::assertSame(0, $this->json()['total']);
        $this->call('GET', "/api/events/occurrences/{$this->occurrence}/attendance?status=EXPECTED", $this->attendanceReader);
        self::assertSame(['Ben Mabasa'], array_column($this->json()['attendees'], 'name'));
        foreach (['status=NOPE', 'limit=0', 'offset=100001'] as $query) {
            $this->call('GET', "/api/events/occurrences/{$this->occurrence}/attendance?$query", $this->attendanceReader);
            self::assertSame(400, $this->code(), $query);
        }
        $this->call('GET', '/api/events/occurrences/'.Uuid::v4()->toRfc4122().'/attendance', $this->attendanceReader);
        self::assertSame(404, $this->code());
    }

    public function testConfirmedPeopleWhoNeverArrivedAreAbsentOnlyAfterTheOccurrenceEnds(): void
    {
        $registration = $this->booking('Ada');
        $this->connection->executeStatement("UPDATE event_occurrences SET starts_at = now() - interval '3 hours', ends_at = now() - interval '90 minutes' WHERE id = ?", [$this->occurrence]);

        $this->call('GET', "/api/events/occurrences/{$this->occurrence}/attendance", $this->attendanceReader);

        self::assertSame('ABSENT', $this->json()['attendees'][0]['status']);
        self::assertSame(1, $this->json()['counts']['absent']);
        self::assertFalse($this->json()['admissionOpen']);
        $this->call('GET', "/api/events/registrations/$registration/attendance-history", $this->attendanceReader);
        self::assertSame(404, $this->code(), 'no attendance record, no history');
    }

    public function testTheAttendanceReportAndCsvExport(): void
    {
        $registration = $this->booking('Ada');
        $this->booking('Ben');
        $this->startsIn('1 hour');
        $this->checkIn($registration);
        $from = rawurlencode((new DateTimeImmutable('-1 day'))->format('Y-m-d\TH:i:s\Z'));
        $until = rawurlencode((new DateTimeImmutable('+30 days'))->format('Y-m-d\TH:i:s\Z'));

        $this->call('GET', "/api/events/attendance-report?from=$from&until=$until", $this->attendanceReader);
        self::assertSame(200, $this->code(), $this->message());
        self::assertSame(1, $this->json()['totals']['occurrences']);
        self::assertSame(['occurrences' => 1, 'confirmed' => 2, 'waitlisted' => 0, 'cancelled' => 0, 'attended' => 1, 'absent' => 0], $this->json()['totals']);
        self::assertSame('Sunday service', $this->json()['occurrences'][0]['title']);
        self::assertSame(1, $this->json()['occurrences'][0]['counts']['attended']);
        $this->call('GET', "/api/events/attendance-report?from=$from&until=$until&search=zzz", $this->attendanceReader);
        self::assertSame(0, $this->json()['totals']['occurrences']);

        $this->call('GET', "/api/events/attendance-report.csv?from=$from&until=$until", $this->attendanceReader);
        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/csv;charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertStringContainsString('attachment; filename=event-attendance.csv', (string) $response->headers->get('Content-Disposition'));
        $lines = explode("\r\n", (string) $response->getContent());
        self::assertSame('Event,Occurrence,Starts at,Ends at,Time zone,Venue,Status,Confirmed,Waitlisted,Cancelled,Attended,Absent', $lines[0]);
        self::assertStringStartsWith('"Sunday service","'.$this->occurrence.'"', $lines[1]);
        self::assertStringEndsWith(',"SCHEDULED",2,0,0,1,0', $lines[1]);

        $late = rawurlencode((new DateTimeImmutable('+400 days'))->format('Y-m-d\TH:i:s\Z'));
        foreach (["from=$from&until=$late", "from=$until&until=$from", "from=$from", 'from=x&until=y', "from=$from&until=$until&limit=0", "from=$from&until=$until&eventId=x"] as $query) {
            $this->call('GET', "/api/events/attendance-report?$query", $this->attendanceReader);
            self::assertSame(400, $this->code(), $query);
        }
        $this->call('GET', "/api/events/attendance-report.csv?from=$from&until=$late", $this->attendanceReader);
        self::assertSame(400, $this->code());
    }

    public function testPermissionsAreEnforced(): void
    {
        $registration = $this->booking('Ada');
        $this->startsIn('1 hour');
        $writes = [
            ['POST', "/api/events/registrations/$registration/check-in", ['requestId' => Uuid::v4()->toRfc4122(), 'expectedVersion' => 0]],
            ['POST', "/api/events/occurrences/{$this->occurrence}/walk-ins", ['requestId' => Uuid::v4()->toRfc4122(), 'personId' => Uuid::v4()->toRfc4122()]],
            ['POST', "/api/events/registrations/$registration/attendance-correction", ['expectedVersion' => 0, 'status' => 'VOIDED', 'reason' => 'x']],
        ];
        foreach ($writes as [$method, $uri, $body]) {
            $this->call($method, $uri, null, $body);
            self::assertSame(401, $this->code(), $uri);
            $this->call($method, $uri, $this->attendanceReader, $body);
            self::assertSame(403, $this->code(), $uri);
        }
        $reads = ["/api/events/occurrences/{$this->occurrence}/attendance", "/api/events/registrations/$registration/attendance-history", '/api/events/attendance-report?from=a&until=b', '/api/events/attendance-report.csv?from=a&until=b'];
        foreach ($reads as $uri) {
            $this->call('GET', $uri);
            self::assertSame(401, $this->code(), $uri);
            $this->call('GET', $uri, $this->reader);
            self::assertSame(403, $this->code(), $uri);
        }
        self::assertSame(0, $this->rows('event_attendance'));
    }
}
