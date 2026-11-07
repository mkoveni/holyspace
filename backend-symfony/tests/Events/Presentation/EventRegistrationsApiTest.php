<?php

declare(strict_types=1);

namespace App\Tests\Events\Presentation;

use Symfony\Component\Uid\Uuid;

final class EventRegistrationsApiTest extends EventsApiTestCase
{
    private string $occurrence;

    protected function setUp(): void
    {
        parent::setUp();
        $this->event();
        $this->occurrence = (string) $this->connection->fetchOne('SELECT id FROM event_occurrences ORDER BY starts_at LIMIT 1');
    }

    private function open(?int $capacity = null, bool $waitlist = true, int $version = 0): void
    {
        $this->call('PUT', "/api/events/occurrences/{$this->occurrence}/registration", $this->registrar, ['expectedVersion' => $version, 'open' => true, 'capacity' => $capacity, 'waitlistEnabled' => $waitlist, 'reason' => 'Planning']);
        self::assertSame(204, $this->code());
    }

    private function book(string $person, ?int $version = null, ?string $request = null): string
    {
        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/registrations", $this->registrar, ['requestId' => $request ?? Uuid::v4()->toRfc4122(), 'personId' => $person, 'expectedVersion' => $version]);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    private function state(string $registration): string
    {
        return (string) $this->connection->fetchOne('SELECT status FROM event_registrations WHERE id = ?', [$registration]);
    }

    public function testConfiguringTheRegistrationPolicy(): void
    {
        $this->open(2);

        $row = $this->connection->fetchAssociative('SELECT * FROM event_registration_policies WHERE occurrence_id = ?', [$this->occurrence]);
        self::assertIsArray($row);
        self::assertTrue($row['open']);
        self::assertSame(2, $row['capacity']);
        self::assertSame(1, $row['version']);
        self::assertSame(1, $this->rows('event_audit', "action = 'registration.policy.updated' AND target_id = '{$this->occurrence}'"));

        $url = "/api/events/occurrences/{$this->occurrence}/registration";
        $body = ['expectedVersion' => 1, 'open' => true, 'capacity' => 2, 'waitlistEnabled' => true, 'reason' => 'x'];
        $this->call('PUT', $url, $this->registrar, ['expectedVersion' => 0] + $body);
        self::assertSame(409, $this->code());
        $this->call('PUT', $url, $this->registrar, ['capacity' => 0] + $body);
        self::assertSame(400, $this->code());
        $this->call('PUT', $url, $this->registrar, ['capacity' => 10001] + $body);
        self::assertSame(400, $this->code());
        $this->call('PUT', $url, $this->registrar, ['reason' => ' '] + $body);
        self::assertSame(400, $this->code());
        $this->call('PUT', $url, $this->registrar, array_diff_key($body, ['expectedVersion' => 1]));
        self::assertSame(400, $this->code());
        $this->call('PUT', '/api/events/occurrences/'.Uuid::v4()->toRfc4122().'/registration', $this->registrar, $body);
        self::assertSame(404, $this->code());
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT version FROM event_registration_policies WHERE occurrence_id = ?', [$this->occurrence]));
    }

    public function testBookingFillsCapacityThenWaitlistsAndCancellingPromotesInQueueOrder(): void
    {
        $this->open(1);
        [$ada, $ben, $cara] = [$this->person('Ada'), $this->person('Ben'), $this->person('Cara')];
        $first = $this->book($ada);
        $second = $this->book($ben);
        $third = $this->book($cara);

        self::assertSame(['CONFIRMED', 'WAITLISTED', 'WAITLISTED'], [$this->state($first), $this->state($second), $this->state($third)]);
        self::assertSame([1, 2, 3], array_map('intval', $this->connection->fetchFirstColumn('SELECT queue_position FROM event_registrations ORDER BY queue_position')));

        $this->call('POST', "/api/events/registrations/$first/cancel", $this->registrar, ['expectedVersion' => 0, 'reason' => 'Unwell']);
        self::assertSame(204, $this->code());
        self::assertSame(['CANCELLED', 'CONFIRMED', 'WAITLISTED'], [$this->state($first), $this->state($second), $this->state($third)]);
        self::assertSame(1, $this->rows('event_registration_history', "action = 'PROMOTED' AND registration_id = '$second'"));

        $this->call('POST', "/api/events/registrations/$second/cancel", $this->registrar, ['expectedVersion' => 1, 'reason' => 'Unwell']);
        self::assertSame(['CANCELLED', 'CANCELLED', 'CONFIRMED'], [$this->state($first), $this->state($second), $this->state($third)]);
    }

    public function testASingleCancelCanPromoteSeveralPlacesAtOnce(): void
    {
        $this->open(2);
        $people = array_map(fn (string $n): string => $this->person($n), ['Ada', 'Ben', 'Cara', 'Dan', 'Eve']);
        $ids = array_map(fn (string $p): string => $this->book($p), $people);

        $this->call('PUT', "/api/events/occurrences/{$this->occurrence}/registration", $this->registrar, ['expectedVersion' => 6, 'open' => true, 'capacity' => 4, 'waitlistEnabled' => true, 'reason' => 'Bigger room']);

        self::assertSame(204, $this->code());
        self::assertSame(['CONFIRMED', 'CONFIRMED', 'CONFIRMED', 'CONFIRMED', 'WAITLISTED'], array_map($this->state(...), $ids));
    }

    public function testAFullOccurrenceWithoutAWaitlistRefusesBookings(): void
    {
        $this->open(1, waitlist: false);
        $this->book($this->person('Ada'));

        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/registrations", $this->registrar, ['requestId' => Uuid::v4()->toRfc4122(), 'personId' => $this->person('Ben')]);

        self::assertSame(409, $this->code());
        self::assertSame(1, $this->rows('event_registrations'));
    }

    public function testARepeatedRequestIsIdempotentAndAReusedIdWithAnotherPersonConflicts(): void
    {
        $this->open();
        $request = Uuid::v4()->toRfc4122();
        $ada = $this->person('Ada');
        $first = $this->book($ada, request: $request);

        self::assertSame($first, $this->book($ada, request: $request));
        self::assertSame(1, $this->rows('event_registrations'));
        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/registrations", $this->registrar, ['requestId' => $request, 'personId' => $this->person('Ben')]);
        self::assertSame(409, $this->code());
    }

    public function testACancelledPersonRejoinsTheSameRowWithItsVersion(): void
    {
        $this->open();
        $ada = $this->person('Ada');
        $row = $this->book($ada);
        $this->call('POST', "/api/events/registrations/$row/cancel", $this->registrar, ['expectedVersion' => 0, 'reason' => 'Away']);

        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/registrations", $this->registrar, ['requestId' => Uuid::v4()->toRfc4122(), 'personId' => $ada]);
        self::assertSame(409, $this->code(), 'a person with a record must send its version');
        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/registrations", $this->registrar, ['requestId' => Uuid::v4()->toRfc4122(), 'personId' => $ada, 'expectedVersion' => 0]);
        self::assertSame(409, $this->code(), 'stale version');

        self::assertSame($row, $this->book($ada, 1));
        self::assertSame('CONFIRMED', $this->state($row));
        self::assertSame(1, $this->rows('event_registrations'));
        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/registrations", $this->registrar, ['requestId' => Uuid::v4()->toRfc4122(), 'personId' => $ada, 'expectedVersion' => 2]);
        self::assertSame(409, $this->code(), 'already registered');
    }

    public function testBookingIsRefusedWhenClosedOrNotBookable(): void
    {
        $ada = $this->person('Ada');
        $url = "/api/events/occurrences/{$this->occurrence}/registrations";
        $this->call('POST', $url, $this->registrar, ['requestId' => Uuid::v4()->toRfc4122(), 'personId' => $ada]);
        self::assertSame(409, $this->code(), 'closed');

        $this->open();
        $this->call('POST', $url, $this->registrar, ['requestId' => Uuid::v4()->toRfc4122(), 'personId' => Uuid::v4()->toRfc4122()]);
        self::assertSame(404, $this->code(), 'unknown person');
        $this->call('POST', '/api/events/occurrences/'.Uuid::v4()->toRfc4122().'/registrations', $this->registrar, ['requestId' => Uuid::v4()->toRfc4122(), 'personId' => $ada]);
        self::assertSame(404, $this->code(), 'unknown occurrence');
        $this->connection->executeStatement("UPDATE event_occurrences SET status = 'CANCELLED'");
        $this->call('POST', $url, $this->registrar, ['requestId' => Uuid::v4()->toRfc4122(), 'personId' => $ada]);
        self::assertSame(409, $this->code(), 'cancelled occurrence');
        self::assertSame(0, $this->rows('event_registrations'));
    }

    public function testStaleCancelsAndPresentPeopleChangeNothing(): void
    {
        $this->open();
        $row = $this->book($this->person('Ada'));

        $this->call('POST', "/api/events/registrations/$row/cancel", $this->registrar, ['expectedVersion' => 7, 'reason' => 'x']);
        self::assertSame(409, $this->code());
        $this->connection->insert('event_attendance', ['id' => Uuid::v4()->toRfc4122(), 'registration_id' => $row, 'arrived_at' => '2026-01-01 00:00:00+00', 'status' => 'PRESENT', 'version' => 0]);
        $this->call('POST', "/api/events/registrations/$row/cancel", $this->registrar, ['expectedVersion' => 0, 'reason' => 'x']);
        self::assertSame(409, $this->code());
        $this->call('POST', "/api/events/registrations/$row/cancel", $this->registrar, ['expectedVersion' => 0, 'reason' => ' ']);
        self::assertSame(400, $this->code());
        $this->call('POST', '/api/events/registrations/'.Uuid::v4()->toRfc4122().'/cancel', $this->registrar, ['expectedVersion' => 0, 'reason' => 'x']);
        self::assertSame(404, $this->code());
        self::assertSame('CONFIRMED', $this->state($row));
        self::assertSame(0, $this->rows('event_registration_history', "action = 'CANCELLED'"));
    }

    public function testRegistrationsAreListedWithNamesCountsAndHistory(): void
    {
        $this->open(1);
        $ada = $this->book($this->person('Ada'));
        $this->book($this->person('Ben'));

        $this->call('GET', "/api/events/occurrences/{$this->occurrence}/registrations", $this->registrationReader);
        self::assertSame(200, $this->code());
        self::assertSame(2, $this->json()['total']);
        self::assertSame(['confirmed' => 1, 'waitlisted' => 1, 'cancelled' => 0], $this->json()['counts']);
        self::assertSame('Ada Mabasa', $this->json()['registrations'][0]['name']);
        self::assertSame(1, $this->json()['registrations'][0]['registration']['position']);
        $this->call('GET', "/api/events/occurrences/{$this->occurrence}/registrations?status=WAITLISTED", $this->registrationReader);
        self::assertSame(1, $this->json()['total']);
        foreach (['status=NOPE', 'limit=0', 'offset=100001'] as $query) {
            $this->call('GET', "/api/events/occurrences/{$this->occurrence}/registrations?$query", $this->registrationReader);
            self::assertSame(400, $this->code(), $query);
        }

        $this->call('GET', "/api/events/registrations/$ada/history", $this->registrationReader);
        self::assertSame(200, $this->code());
        self::assertSame('REGISTERED', $this->json()[0]['action']);
        self::assertSame('CONFIRMED', $this->json()[0]['reason']);
        $this->call('GET', '/api/events/registrations/'.Uuid::v4()->toRfc4122().'/history', $this->registrationReader);
        self::assertSame(404, $this->code());
        $this->call('GET', '/api/events/occurrences/'.Uuid::v4()->toRfc4122().'/registrations', $this->registrationReader);
        self::assertSame(404, $this->code());
    }

    public function testPermissionsAreEnforced(): void
    {
        $ada = $this->person('Ada');
        $this->open();
        $row = $this->book($ada);
        $writes = [
            ['PUT', "/api/events/occurrences/{$this->occurrence}/registration", ['expectedVersion' => 0, 'reason' => 'x']],
            ['POST', "/api/events/occurrences/{$this->occurrence}/registrations", ['requestId' => Uuid::v4()->toRfc4122(), 'personId' => $ada]],
            ['POST', "/api/events/registrations/$row/cancel", ['expectedVersion' => 0, 'reason' => 'x']],
        ];
        foreach ($writes as [$method, $uri, $body]) {
            $this->call($method, $uri, null, $body);
            self::assertSame(401, $this->code(), "$method $uri");
            $this->call($method, $uri, $this->registrationReader, $body);
            self::assertSame(403, $this->code(), "$method $uri");
        }
        foreach (["/api/events/occurrences/{$this->occurrence}/registrations", "/api/events/registrations/$row/history"] as $uri) {
            $this->call('GET', $uri);
            self::assertSame(401, $this->code(), $uri);
            $this->call('GET', $uri, $this->reader);
            self::assertSame(403, $this->code(), $uri);
        }
        self::assertSame('CONFIRMED', $this->state($row));
    }
}
