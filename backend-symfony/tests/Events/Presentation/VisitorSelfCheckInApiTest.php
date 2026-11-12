<?php

declare(strict_types=1);

namespace App\Tests\Events\Presentation;

use DateTimeImmutable;

final class VisitorSelfCheckInApiTest extends EventsApiTestCase
{
    private string $occurrence;
    private string $door;
    private string $welcome;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountWith('door', ['events.attendance.manage']);
        $this->accountWith('welcome', ['events.visitors.read']);
        $this->door = $this->login('door');
        $this->welcome = $this->login('welcome');
        $this->event($this->draft((new DateTimeImmutable('+2 days'))->format('Y-m-d'), 1));
        $this->occurrence = (string) $this->connection->fetchOne('SELECT id FROM event_occurrences LIMIT 1');
        $this->connection->executeStatement('UPDATE event_registration_policies SET open = TRUE WHERE occurrence_id = ?', [$this->occurrence]);
    }

    private function startsIn(string $interval): void
    {
        $this->connection->executeStatement("UPDATE event_occurrences SET starts_at = now() + interval '$interval', ends_at = now() + interval '$interval' + interval '90 minutes' WHERE id = ?", [$this->occurrence]);
    }

    private function enableLink(): string
    {
        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/visit-link", $this->door, ['action' => 'ENABLE']);
        self::assertSame(200, $this->code(), $this->message());

        return (string) $this->json()['token'];
    }

    /** @param array<string, mixed> $extra */
    private function visit(string $token, array $extra = []): void
    {
        $this->call('POST', "/api/public/visits/$token", null, $extra + [
            'firstName' => 'Thandi', 'lastName' => 'Nkosi', 'mobile' => '082 555 0101', 'consent' => true, 'consentVersion' => '2026-11',
        ]);
    }

    public function testVisitorChecksInWithoutAnAccount(): void
    {
        $this->startsIn('1 hour');
        $token = $this->enableLink();

        $this->call('GET', "/api/public/visits/$token");
        self::assertSame(200, $this->code(), $this->message());
        self::assertTrue($this->json()['open']);
        self::assertSame('Sunday service', $this->json()['title']);

        $this->visit($token, ['howHeard' => 'FRIEND_OR_FAMILY', 'partyAdults' => 2]);
        self::assertSame(201, $this->code(), $this->message());
        self::assertTrue($this->json()['admitted']);
        self::assertArrayNotHasKey('personId', $this->json());

        self::assertSame(1, $this->rows('people_persons', "first_name = 'Thandi' AND membership_type = 'VISITOR'"));
        self::assertSame(1, $this->rows('event_attendance', "status = 'PRESENT'"));
        self::assertSame(1, $this->rows('event_visitor_check_ins', "review_status = 'PENDING' AND first_time AND consent_version = '2026-11' AND party_adults = 2"));
        self::assertSame(1, $this->rows('event_audit', "action = 'visitor.self_checked_in'"));
    }

    public function testSubmittingAgainOrWithAnotherSpellingOfTheNumberDoesNotDuplicate(): void
    {
        $this->startsIn('1 hour');
        $token = $this->enableLink();
        $this->visit($token);
        $this->visit($token);
        self::assertSame(201, $this->code(), $this->message());
        $this->visit($token, ['mobile' => '+27825550101']);
        self::assertSame(201, $this->code(), $this->message());

        self::assertSame(1, $this->rows('people_persons', "first_name = 'Thandi'"));
        self::assertSame(1, $this->rows('event_attendance'));
        self::assertSame(1, $this->rows('event_visitor_check_ins'));
    }

    public function testAnExistingPersonIsAttachedAndNeverEdited(): void
    {
        $existing = $this->person('Ada');
        $this->call('POST', "/api/people/$existing/contacts", $this->directory, ['kind' => 'EMAIL', 'value' => 'ada@example.test', 'primary' => true, 'expectedVersion' => 0]);
        self::assertSame(201, $this->code(), $this->message());
        $this->startsIn('1 hour');
        $token = $this->enableLink();

        $this->visit($token, ['firstName' => 'Someone', 'lastName' => 'Else', 'mobile' => null, 'email' => 'ADA@example.test']);

        self::assertSame(201, $this->code(), $this->message());
        self::assertSame(0, $this->rows('people_persons', "first_name = 'Someone'"));
        self::assertSame(1, $this->rows('people_persons', "id = '$existing' AND first_name = 'Ada' AND version = 1"));
        self::assertSame(1, $this->rows('event_registrations', "person_id = '$existing'"));
        self::assertSame(1, $this->rows('event_visitor_check_ins', 'NOT first_time'));
    }

    public function testRefusalsSaveNothing(): void
    {
        $token = $this->enableLink();
        $this->visit($token);
        self::assertSame(409, $this->code(), 'more than two hours before the start');

        $this->startsIn('1 hour');
        $this->visit($token, ['consent' => false]);
        self::assertSame(400, $this->code());
        $this->visit($token, ['mobile' => null, 'email' => null]);
        self::assertSame(400, $this->code());
        $this->visit($token.'x');
        self::assertSame(404, $this->code(), 'forged signature');
        $this->visit($token, ['website' => 'https://spam.example']);
        self::assertSame(201, $this->code(), 'a bot is told it worked');

        self::assertSame(0, $this->rows('people_persons', "first_name = 'Thandi'"));
        self::assertSame(0, $this->rows('event_attendance'));
        self::assertSame(0, $this->rows('event_visitor_check_ins'));
    }

    public function testRotatingOrDisablingTheLinkStopsTheOldCode(): void
    {
        $this->startsIn('1 hour');
        $old = $this->enableLink();
        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/visit-link", $this->door, ['action' => 'ROTATE']);
        $new = (string) $this->json()['token'];
        self::assertNotSame($old, $new);

        $this->visit($old);
        self::assertSame(404, $this->code());
        $this->call('GET', "/api/public/visits/$old");
        self::assertSame(404, $this->code());
        $this->visit($new);
        self::assertSame(201, $this->code(), $this->message());

        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/visit-link", $this->door, ['action' => 'DISABLE']);
        self::assertFalse($this->json()['enabled']);
        self::assertNull($this->json()['token']);
        $this->visit($new, ['mobile' => '0825550102']);
        self::assertSame(404, $this->code());
    }

    public function testTheLinkNeedsOpenRegistration(): void
    {
        $this->connection->executeStatement('UPDATE event_registration_policies SET open = FALSE WHERE occurrence_id = ?', [$this->occurrence]);
        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/visit-link", $this->door, ['action' => 'ENABLE']);
        self::assertSame(409, $this->code());
    }

    public function testStaffPermissionsAreSeparate(): void
    {
        $this->startsIn('1 hour');
        $this->enableLink();
        $this->call('GET', "/api/events/occurrences/{$this->occurrence}/visit-link", $this->welcome);
        self::assertSame(403, $this->code());
        $this->call('GET', "/api/events/occurrences/{$this->occurrence}/visitors", $this->door);
        self::assertSame(403, $this->code(), 'contact details need events.visitors.read');
        $this->call('GET', "/api/events/occurrences/{$this->occurrence}/visitors");
        self::assertSame(401, $this->code());
        $this->call('GET', "/api/events/occurrences/{$this->occurrence}/visitors", $this->welcome);
        self::assertSame(200, $this->code(), $this->message());
    }

    public function testStaffConfirmOrRemoveAVisitor(): void
    {
        $this->startsIn('1 hour');
        $token = $this->enableLink();
        $this->visit($token);
        $this->visit($token, ['firstName' => 'Sipho', 'lastName' => 'Dlamini', 'mobile' => '0825550999']);

        $this->call('GET', "/api/events/occurrences/{$this->occurrence}/visitors?status=PENDING", $this->welcome);
        self::assertSame(2, $this->json()['pending']);
        /** @var list<array<string, mixed>> $visitors */
        $visitors = $this->json()['visitors'];
        $byName = array_column($visitors, null, 'fullName');
        $thandi = $byName['Thandi Nkosi'];
        $sipho = $byName['Sipho Dlamini'];
        self::assertSame('0825550999', $sipho['mobile']);
        self::assertSame('PRESENT', $sipho['attendanceStatus']);

        $this->call('POST', "/api/events/registrations/{$thandi['registrationId']}/visitor-review", $this->door, ['decision' => 'CONFIRM', 'reason' => 'Met at the door']);
        self::assertSame(204, $this->code());
        $this->call('POST', "/api/events/registrations/{$sipho['registrationId']}/visitor-review", $this->door, ['decision' => 'REMOVE', 'reason' => 'Test entry', 'expectedAttendanceVersion' => $sipho['attendanceVersion']]);
        self::assertSame(204, $this->code());

        self::assertSame(1, $this->rows('event_visitor_check_ins', "review_status = 'CONFIRMED'"));
        self::assertSame(1, $this->rows('event_visitor_check_ins', "review_status = 'REMOVED'"));
        self::assertSame(1, $this->rows('event_attendance', "status = 'VOIDED'"));
        self::assertSame(1, $this->rows('event_attendance_history', "action = 'VOIDED'"));
        $this->call('POST', "/api/events/registrations/{$sipho['registrationId']}/visitor-review", $this->door, ['decision' => 'CONFIRM', 'reason' => 'Oops']);
        self::assertSame(409, $this->code(), 'a removed visitor cannot be confirmed');
    }
}
