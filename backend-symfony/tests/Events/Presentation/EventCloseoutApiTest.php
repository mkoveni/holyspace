<?php

declare(strict_types=1);

namespace App\Tests\Events\Presentation;

use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final class EventCloseoutApiTest extends EventsApiTestCase
{
    private string $occurrence;
    private string $coordinator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->event($this->draft((new DateTimeImmutable('+2 days'))->format('Y-m-d'), 1));
        $this->occurrence = (string) $this->connection->fetchOne('SELECT id FROM event_occurrences LIMIT 1');
        $this->coordinator = $this->login('manager');
        $this->connection->executeStatement("UPDATE event_registration_policies SET open = TRUE WHERE occurrence_id = '{$this->occurrence}'");
    }

    private function finished(): void
    {
        $this->connection->executeStatement("UPDATE event_occurrences SET starts_at = now() - interval '3 hours', ends_at = now() - interval '90 minutes' WHERE id = ?", [$this->occurrence]);
    }

    private function booking(string $name): string
    {
        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/registrations", $this->registrar, ['requestId' => Uuid::v4()->toRfc4122(), 'personId' => $this->person($name)]);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    private function readiness(): void
    {
        $this->call('GET', "/api/events/occurrences/{$this->occurrence}/readiness", $this->coordinator);
        self::assertSame(200, $this->code(), $this->message());
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function closeBody(string $hash, int $version = 0, array $extra = []): array
    {
        return array_replace(['expectedVersion' => $version, 'reviewHash' => $hash, 'note' => 'Went well', 'followUp' => '', 'attendanceReviewed' => true, 'followUpReviewed' => true], $extra);
    }

    private function reviewHash(): string
    {
        $this->readiness();

        return (string) $this->json()['review']['hash'];
    }

    public function testReadinessShowsTheChecklistTotalsAndStaffing(): void
    {
        $this->booking('Ada');
        $this->readiness();
        $view = $this->json();

        self::assertSame(['venue', 'registration', 'staffing', 'reminders', 'deliveries'], array_column($view['checks'], 'key'));
        self::assertSame('READY', $view['checks'][0]['status']);
        self::assertSame('Main hall · Venue available', $view['checks'][0]['detail']);
        self::assertSame('Open · 1 confirmed · No seat limit · 0 waitlisted', $view['checks'][1]['detail']);
        self::assertSame('REVIEW', $view['checks'][2]['status']);
        self::assertSame('No duties planned. Confirm whether staffing is needed.', $view['checks'][2]['detail']);
        self::assertSame('0 of 1 confirmed bookings have a reminder approved for the current schedule. Approval is not delivery.', $view['checks'][3]['detail']);
        self::assertSame('READY', $view['checks'][4]['status']);
        self::assertSame(['shifts' => 0, 'positions' => 0, 'confirmed' => 0, 'ready' => 0, 'proposed' => 0, 'gaps' => 0, 'complete' => true], $view['staffing']);
        self::assertSame(1, $view['review']['attendance']['confirmed']);
        self::assertSame(0, $view['review']['notices']['approved']);
        self::assertTrue($view['review']['notices']['complete']);
        self::assertFalse($view['review']['canClose']);
        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $view['review']['hash']);
        self::assertSame('OPEN', $view['closeout']['status']);
        self::assertNull($view['closeout']['updatedBy']);
        self::assertFalse($view['changedSinceCloseout']);
    }

    public function testTheReviewHashMovesWhenBookingsOrAttendanceChange(): void
    {
        $first = $this->reviewHash();
        self::assertSame($first, $this->reviewHash());
        $registration = $this->booking('Ada');
        $afterBooking = $this->reviewHash();
        self::assertNotSame($first, $afterBooking);

        $this->connection->insert('event_attendance', ['id' => Uuid::v4()->toRfc4122(), 'registration_id' => $registration, 'arrived_at' => '2026-01-01 00:00:00+00', 'status' => 'PRESENT', 'version' => 0]);
        self::assertNotSame($afterBooking, $this->reviewHash());
    }

    public function testClosingAfterTheEndRecordsTheReviewAndHistory(): void
    {
        $present = $this->booking('Ada');
        $this->booking('Ben');
        $this->connection->insert('event_attendance', ['id' => Uuid::v4()->toRfc4122(), 'registration_id' => $present, 'arrived_at' => '2026-01-01 00:00:00+00', 'status' => 'PRESENT', 'version' => 0]);
        $this->finished();
        $hash = $this->reviewHash();
        self::assertTrue($this->json()['review']['canClose']);

        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/closeout", $this->coordinator, $this->closeBody($hash));
        self::assertSame(204, $this->code());

        $row = $this->connection->fetchAssociative('SELECT * FROM event_closeouts WHERE occurrence_id = ?', [$this->occurrence]);
        self::assertIsArray($row);
        self::assertSame('CLOSED', $row['status']);
        self::assertSame(1, $row['version']);
        self::assertSame($hash, trim((string) $row['review_hash']));
        self::assertSame(1, $this->rows('event_audit', "action = 'occurrence.closeout.closed' AND target_id = '{$this->occurrence}'"));

        $this->call('GET', "/api/events/occurrences/{$this->occurrence}/closeout/history", $this->coordinator);
        self::assertSame(200, $this->code());
        self::assertSame('CLOSED', $this->json()[0]['action']);
        self::assertSame(1, $this->json()[0]['attended']);
        self::assertSame(1, $this->json()[0]['absent']);
        self::assertSame('Manager', $this->json()[0]['actorName']);
        $this->readiness();
        self::assertSame('CLOSED', $this->json()['closeout']['status']);
        self::assertFalse($this->json()['changedSinceCloseout']);
    }

    public function testClosingBeforeTheEndOrWithAStaleHashOrWithoutAcknowledgementsIsRefused(): void
    {
        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/closeout", $this->coordinator, $this->closeBody($this->reviewHash()));
        self::assertSame(409, $this->code(), 'still in the future');

        $this->finished();
        $hash = $this->reviewHash();
        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/closeout", $this->coordinator, $this->closeBody(str_repeat('0', 64)));
        self::assertSame(409, $this->code(), 'stale hash');
        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/closeout", $this->coordinator, $this->closeBody($hash, extra: ['attendanceReviewed' => false]));
        self::assertSame(400, $this->code());
        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/closeout", $this->coordinator, $this->closeBody($hash, 4));
        self::assertSame(409, $this->code(), 'stale closeout version');
        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/closeout", $this->coordinator, $this->closeBody($hash, extra: ['note' => ' ']));
        self::assertSame(400, $this->code());
        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/closeout", $this->coordinator, array_diff_key($this->closeBody($hash), ['expectedVersion' => 1]));
        self::assertSame(400, $this->code());
        self::assertSame(0, $this->rows('event_closeouts'));
        self::assertSame(0, $this->rows('event_closeout_history'));
    }

    public function testFollowUpIsRequiredWhileWaitlistedPeopleOrNoticeProblemsRemain(): void
    {
        $this->connection->executeStatement("UPDATE event_registration_policies SET capacity = 1 WHERE occurrence_id = '{$this->occurrence}'");
        $this->booking('Ada');
        $this->booking('Ben');
        $this->finished();

        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/closeout", $this->coordinator, $this->closeBody($this->reviewHash()));
        self::assertSame(400, $this->code(), 'a waitlisted person needs follow-up text');
        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/closeout", $this->coordinator, $this->closeBody($this->reviewHash(), extra: ['followUp' => 'Offer next week']));
        self::assertSame(204, $this->code());
    }

    public function testANoticeWithoutADeliveryCountsAsNeedingAttention(): void
    {
        $registration = $this->booking('Ada');
        $this->connection->insert('event_notifications', [
            'id' => Uuid::v4()->toRfc4122(), 'registration_id' => $registration, 'registration_version' => 0, 'schedule_revision' => 0, 'event_version' => 0,
            'request_hash' => str_repeat('a', 64), 'purpose' => 'REMINDER', 'contact_id' => Uuid::v4()->toRfc4122(), 'review_hash' => str_repeat('b', 64),
            'verification_evidence' => 'x', 'created_by' => Uuid::v4()->toRfc4122(), 'created_at' => '2026-01-01 00:00:00+00', 'delivery_id' => Uuid::v4()->toRfc4122(),
        ]);

        $this->readiness();

        self::assertSame(1, $this->json()['review']['notices']['approved']);
        self::assertSame(1, $this->json()['review']['notices']['failed']);
        self::assertSame('REVIEW', $this->json()['checks'][4]['status']);
        self::assertSame('0 pending · 1 need attention · 0 captured locally', $this->json()['checks'][4]['detail']);
        self::assertSame(1, $this->json()['review']['notices']['remindersApproved'], 'the reminder matches the current booking, schedule and event versions');
        self::assertSame('READY', $this->json()['checks'][3]['status']);
    }

    public function testALateAttendanceCorrectionMarksTheCloseoutChangedAndReopeningKeepsHistory(): void
    {
        $registration = $this->booking('Ada');
        $attendance = Uuid::v4()->toRfc4122();
        $this->connection->insert('event_attendance', ['id' => $attendance, 'registration_id' => $registration, 'arrived_at' => '2026-01-01 00:00:00+00', 'status' => 'PRESENT', 'version' => 0]);
        $this->finished();
        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/closeout", $this->coordinator, $this->closeBody($this->reviewHash()));
        self::assertSame(204, $this->code());

        $this->accountWith('door', ['events.attendance.manage']);
        $this->call('POST', "/api/events/registrations/$registration/attendance-correction", $this->login('door'), ['expectedVersion' => 0, 'status' => 'VOIDED', 'reason' => 'Wrong person']);
        self::assertSame(204, $this->code());

        $this->readiness();
        self::assertTrue($this->json()['changedSinceCloseout']);
        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/closeout", $this->coordinator, $this->closeBody($this->reviewHash(), 1));
        self::assertSame(409, $this->code(), 'already closed');

        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/closeout/reopen", $this->coordinator, ['expectedVersion' => 9, 'reason' => 'x']);
        self::assertSame(409, $this->code());
        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/closeout/reopen", $this->coordinator, ['expectedVersion' => 1, 'reason' => ' ']);
        self::assertSame(400, $this->code());
        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/closeout/reopen", $this->coordinator, ['expectedVersion' => 1, 'reason' => 'Attendance corrected']);
        self::assertSame(204, $this->code());
        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/closeout/reopen", $this->coordinator, ['expectedVersion' => 2, 'reason' => 'again']);
        self::assertSame(409, $this->code(), 'only a closed occurrence can be reopened');

        $this->call('GET', "/api/events/occurrences/{$this->occurrence}/closeout/history", $this->coordinator);
        self::assertSame(['REOPENED', 'CLOSED'], array_column($this->json(), 'action'));
        self::assertSame(0, $this->json()[0]['attended']);
        self::assertSame(1, $this->json()[1]['attended']);
        self::assertSame(1, $this->rows('event_audit', "action = 'occurrence.closeout.open'"));
        $this->call('GET', "/api/events/occurrences/{$this->occurrence}/closeout/history?limit=1&offset=1", $this->coordinator);
        self::assertSame(['CLOSED'], array_column($this->json(), 'action'));
    }

    public function testMissingOccurrencesAndBadPagingAreRejected(): void
    {
        $unknown = Uuid::v4()->toRfc4122();
        foreach (["/api/events/occurrences/$unknown/readiness", "/api/events/occurrences/$unknown/closeout/history"] as $uri) {
            $this->call('GET', $uri, $this->coordinator);
            self::assertSame(404, $this->code(), $uri);
        }
        foreach (['limit=0', 'limit=101', 'offset=100001', 'limit=x'] as $query) {
            $this->call('GET', "/api/events/occurrences/{$this->occurrence}/closeout/history?$query", $this->coordinator);
            self::assertSame(400, $this->code(), $query);
        }
        $this->call('POST', "/api/events/occurrences/$unknown/closeout", $this->coordinator, $this->closeBody('x'));
        self::assertSame(404, $this->code());
        $this->call('POST', "/api/events/occurrences/$unknown/closeout/reopen", $this->coordinator, ['expectedVersion' => 0, 'reason' => 'x']);
        self::assertSame(404, $this->code());
        $this->call('GET', '/api/events/occurrences/nope/readiness', $this->coordinator);
        self::assertSame(400, $this->code());
    }

    public function testPermissionsAreEnforced(): void
    {
        $routes = [
            ['GET', "/api/events/occurrences/{$this->occurrence}/readiness", null],
            ['POST', "/api/events/occurrences/{$this->occurrence}/closeout", $this->closeBody('x')],
            ['POST', "/api/events/occurrences/{$this->occurrence}/closeout/reopen", ['expectedVersion' => 0, 'reason' => 'x']],
            ['GET', "/api/events/occurrences/{$this->occurrence}/closeout/history", null],
        ];
        foreach ($routes as [$method, $uri, $body]) {
            $this->call($method, $uri, null, $body);
            self::assertSame(401, $this->code(), $uri);
            $this->call($method, $uri, $this->reader, $body);
            self::assertSame(403, $this->code(), $uri);
        }
        self::assertSame(0, $this->rows('event_closeouts'));
    }
}
