<?php

declare(strict_types=1);

namespace App\Tests\Events\Infrastructure;

use App\Events\Application\EventRegistrations;
use App\Events\Domain\EventException;
use App\Events\Infrastructure\Persistence\DbalAttendanceStore;
use App\Events\Infrastructure\Persistence\DbalEventJournal;
use App\Events\Infrastructure\Persistence\DbalEventStore;
use App\Events\Infrastructure\Persistence\DbalOccurrenceStore;
use App\Events\Infrastructure\Persistence\DbalRegistrationStore;
use App\Shared\Infrastructure\Persistence\ConnectionFactory;
use App\Shared\Infrastructure\SymfonyIdGenerator;
use App\Tests\Events\Application\RecordingPeople;
use App\Tests\Support\DatabaseTestCase;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

/**
 * Two real database sessions contending for one occurrence. Registration must serialise on the occurrence row, so
 * capacity is never exceeded and the waitlist is decided against committed state.
 */
final class EventConcurrencyTest extends DatabaseTestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string EVENT = '00000000-0000-4000-8000-0000000000e1';
    private const string OCCURRENCE = '00000000-0000-4000-8000-0000000000c1';
    private const array PEOPLE = [
        'ada' => '00000000-0000-4000-8000-0000000000f1',
        'ben' => '00000000-0000-4000-8000-0000000000f2',
        'cara' => '00000000-0000-4000-8000-0000000000f3',
    ];

    private Connection $other;
    private RecordingPeople $people;

    protected function setUp(): void
    {
        parent::setUp();
        $this->other = ConnectionFactory::create((string) getenv('DATABASE_URL'));
        $this->people = new RecordingPeople();
        foreach (self::PEOPLE as $id) {
            $this->people->add($id);
        }
        $venue = '00000000-0000-4000-8000-0000000000a1';
        $series = '00000000-0000-4000-8000-0000000000e2';
        $this->connection->insert('event_venues', ['id' => $venue, 'name' => 'Hall', 'address' => '', 'exclusive' => 0, 'active' => 1, 'version' => 0]);
        $this->connection->insert('event_definitions', ['id' => self::EVENT, 'title' => 'Service', 'type' => 'SERVICE', 'description' => '', 'active' => 1, 'version' => 0]);
        $this->connection->insert('event_series', ['id' => $series, 'event_id' => self::EVENT, 'time_zone' => 'UTC', 'frequency' => 'ONCE', 'repeat_interval' => 1, 'occurrence_count' => 1, 'first_date' => '2099-07-05', 'starts_at' => '10:00', 'ends_at' => '11:00', 'end_day_offset' => 0, 'venue_id' => $venue, 'version' => 0]);
        $this->connection->insert('event_occurrences', ['id' => self::OCCURRENCE, 'event_id' => self::EVENT, 'series_id' => $series, 'origin_date' => '2099-07-05', 'time_zone' => 'UTC', 'starts_at' => '2099-07-05 10:00:00+00', 'ends_at' => '2099-07-05 11:00:00+00', 'venue_id' => $venue, 'venue_name' => 'Hall', 'venue_address' => '', 'exclusive' => 0, 'status' => 'SCHEDULED', 'overridden' => 0, 'version' => 0, 'schedule_revision' => 0]);
        $this->connection->insert('event_registration_policies', ['occurrence_id' => self::OCCURRENCE, 'open' => 1, 'capacity' => 1, 'waitlist_enabled' => 1, 'next_position' => 0, 'version' => 0]);
    }

    protected function tearDown(): void
    {
        foreach ([$this->connection, $this->other] as $session) {
            if ($session->isTransactionActive()) {
                $session->rollBack();
            }
        }
        $this->other->close();
        parent::tearDown();
    }

    private function registrations(Connection $session): EventRegistrations
    {
        $registrations = new DbalRegistrationStore($session);
        $journal = new DbalEventJournal($session);

        return new EventRegistrations(
            new DbalEventStore($session),
            new DbalOccurrenceStore($session),
            $registrations,
            $registrations,
            new DbalAttendanceStore($session),
            $this->people,
            $journal,
            $journal,
            new SymfonyIdGenerator(),
            new MockClock(new DateTimeImmutable('2099-06-01T00:00:00Z')),
        );
    }

    private function book(Connection $session, string $person): string
    {
        return $this->registrations($session)->register(self::ACTOR, Uuid::v4()->toRfc4122(), self::OCCURRENCE, $person, null, false);
    }

    private function stateOf(string $registration): string
    {
        return (string) $this->connection->fetchOne('SELECT status FROM event_registrations WHERE id = ?', [$registration]);
    }

    public function testASecondRegistrationWaitsForTheOccurrenceLockAndThenSeesTheCommittedSeat(): void
    {
        $this->connection->beginTransaction();
        $ada = $this->book($this->connection, self::PEOPLE['ada']);

        $this->other->executeStatement("SET lock_timeout = '300ms'");
        $this->other->beginTransaction();
        try {
            $this->book($this->other, self::PEOPLE['ben']);
            self::fail('The second booking must wait for the first to finish.');
        } catch (DriverException $e) {
            self::assertSame('55P03', $e->getSQLState(), 'the second session waited for the first session\'s lock');
            $this->other->rollBack();
        }

        $this->connection->commit();
        $this->other->executeStatement("SET lock_timeout = '5s'");
        $this->other->beginTransaction();
        $ben = $this->book($this->other, self::PEOPLE['ben']);
        $this->other->commit();

        self::assertSame('CONFIRMED', $this->stateOf($ada));
        self::assertSame('WAITLISTED', $this->stateOf($ben), 'capacity of one is never exceeded');
        self::assertSame([1, 2], array_map('intval', $this->connection->fetchFirstColumn('SELECT queue_position FROM event_registrations ORDER BY queue_position')));
    }

    public function testACancelAndARegistrationDecideCapacityOneAfterTheOther(): void
    {
        $ada = $this->book($this->connection, self::PEOPLE['ada']);
        $ben = $this->book($this->connection, self::PEOPLE['ben']);
        self::assertSame('WAITLISTED', $this->stateOf($ben));

        $this->connection->beginTransaction();
        $this->registrations($this->connection)->cancel(self::ACTOR, $ada, 0, 'Away');

        $this->other->executeStatement("SET lock_timeout = '300ms'");
        $this->other->beginTransaction();
        try {
            $this->book($this->other, self::PEOPLE['cara']);
            self::fail('A booking must wait for an uncommitted cancel and promotion.');
        } catch (DriverException $e) {
            self::assertSame('55P03', $e->getSQLState(), 'the second session waited for the first session\'s lock');
            $this->other->rollBack();
        }
        $this->connection->commit();

        $this->other->executeStatement("SET lock_timeout = '5s'");
        $this->other->beginTransaction();
        $cara = $this->book($this->other, self::PEOPLE['cara']);
        $this->other->commit();

        self::assertSame(['CANCELLED', 'CONFIRMED', 'WAITLISTED'], [$this->stateOf($ada), $this->stateOf($ben), $this->stateOf($cara)], 'the waitlist was promoted before the newcomer was placed');
        self::assertSame(1, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM event_registrations WHERE status = 'CONFIRMED'"));
    }

    public function testTheSchemaStillRefusesDuplicatePeopleAndQueuePositionsIfALockWereEverMissed(): void
    {
        $row = static fn (string $id, string $person, int $position): array => ['id' => $id, 'occurrence_id' => self::OCCURRENCE, 'person_id' => $person, 'status' => 'CONFIRMED', 'queue_position' => $position, 'version' => 0, 'created_at' => '2099-01-01 00:00:00+00', 'updated_at' => '2099-01-01 00:00:00+00'];
        $this->connection->insert('event_registrations', $row('00000000-0000-4000-8000-000000000101', self::PEOPLE['ada'], 1));

        foreach ([$row('00000000-0000-4000-8000-000000000102', self::PEOPLE['ada'], 2), $row('00000000-0000-4000-8000-000000000103', self::PEOPLE['ben'], 1)] as $duplicate) {
            try {
                $this->connection->insert('event_registrations', $duplicate);
                self::fail('Expected a unique violation.');
            } catch (UniqueConstraintViolationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testPeopleCannotBeBookedWhileUnavailable(): void
    {
        $this->people->add(self::PEOPLE['ada'], available: false);

        $this->expectException(EventException::class);
        $this->expectExceptionMessage('Available person was not found.');

        $this->book($this->connection, self::PEOPLE['ada']);
    }
}
