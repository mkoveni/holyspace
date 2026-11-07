<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Sessions\Infrastructure;

use App\ChildrenMinistry\Sessions\Domain\SessionSeries;
use App\ChildrenMinistry\Sessions\Domain\WeeklyRecurrence;
use App\ChildrenMinistry\Sessions\Infrastructure\Persistence\DbalSessionSeriesStore;
use App\ChildrenMinistry\Sessions\Infrastructure\Persistence\DbalSessionStore;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\Shared\Domain\CalendarDate;
use App\Tests\ChildrenMinistry\Participation\Presentation\ChildrenApiTestCase;
use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Tools\DsnParser;
use Symfony\Component\Uid\Uuid;

final class SessionSeriesStoreTest extends ChildrenApiTestCase
{
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();
        $location = $this->create('/locations', ['name' => 'Main', 'timeZone' => 'Africa/Johannesburg']);
        $room = $this->create('/rooms', ['locationId' => $location, 'name' => 'Room 1', 'capacity' => 12]);
        $group = $this->create('/classes', ['programmeId' => $this->programme(), 'name' => 'Juniors', 'minAgeMonths' => 60, 'maxAgeMonths' => 156]);
        $this->source = $this->create('/sessions', ['classId' => $group, 'roomId' => $room, 'startsAt' => '2030-06-02T08:00:00Z', 'endsAt' => '2030-06-02T10:00:00Z', 'seatLimit' => 4]);
    }

    private function series(): SessionSeries
    {
        $source = new DbalSessionStore($this->connection)->ministrySession($this->source);
        self::assertNotNull($source);
        $first = CalendarDate::parse('2030-06-09');

        return SessionSeries::create(Uuid::v4()->toRfc4122(), 'Term', $source, 'Africa/Johannesburg', new WeeklyRecurrence($first, '09:00', '10:30', 2, 3, [$first->plusDays(14), $first]), new DateTimeImmutable('2030-06-01T00:00:00Z'), $this->adminId, Uuid::v4()->toRfc4122(), 'fp');
    }

    public function testASeriesRoundTripsWithItsRecurrenceAndIsFoundByActorAndRequest(): void
    {
        $store = new DbalSessionSeriesStore($this->connection);
        $series = $this->series();
        $store->insert($series);

        $loaded = $store->forUpdate($series->id);

        self::assertNotNull($loaded);
        self::assertSame(['Term', $this->source, 4, 'Africa/Johannesburg', 'fp', false, 0], [$loaded->name, $loaded->sourceSessionId, $loaded->seatLimit, $loaded->timeZone, $loaded->requestFingerprint, $loaded->stopped, $loaded->version]);
        self::assertSame(['2030-06-09', '09:00:00', '10:30:00', 2, 3], [$loaded->recurrence->firstDate->iso(), $loaded->recurrence->startsAt, $loaded->recurrence->endsAt, $loaded->recurrence->intervalWeeks, $loaded->recurrence->occurrences]);
        self::assertSame(['2030-06-09', '2030-06-23'], array_map(static fn (CalendarDate $date): string => $date->iso(), $loaded->recurrence->excludedDates));
        self::assertSame($series->id, $store->request($this->adminId, $series->requestId)?->id);
        self::assertNull($store->request(Uuid::v4()->toRfc4122(), $series->requestId), 'request ids are scoped to the actor');
        self::assertNull($store->forUpdate(Uuid::v4()->toRfc4122()));
    }

    public function testASeriesWithNoExclusionsRoundTrips(): void
    {
        $store = new DbalSessionSeriesStore($this->connection);
        $source = new DbalSessionStore($this->connection)->ministrySession($this->source);
        self::assertNotNull($source);
        $series = SessionSeries::create(Uuid::v4()->toRfc4122(), 'Plain', $source, 'UTC', new WeeklyRecurrence(CalendarDate::parse('2030-06-09'), '09:00', '10:00', 1, 2), new DateTimeImmutable('2030-06-01T00:00:00Z'), $this->adminId, Uuid::v4()->toRfc4122(), 'fp');
        $store->insert($series);

        self::assertSame([], $store->forUpdate($series->id)?->recurrence->excludedDates);
    }

    public function testStoppingIsVersionGuardedAndAStaleUpdateIsAConflict(): void
    {
        $store = new DbalSessionSeriesStore($this->connection);
        $series = $this->series();
        $store->insert($series);
        $store->update($series->stop(0));

        self::assertTrue($store->forUpdate($series->id)?->stopped);
        $this->expectException(ChildrenException::class);
        $this->expectExceptionMessage('The series changed');
        $store->update($series->stop(0));
    }

    public function testLinksListTheSessionsAndSummariesCountThemByStatus(): void
    {
        $store = new DbalSessionSeriesStore($this->connection);
        $series = $this->series();
        $store->insert($series);
        $store->link($series->id, $this->source, CalendarDate::parse('2030-06-02'));

        self::assertSame([$this->source], $store->sessionIds($series->id));
        $summary = $store->summary($series->id);
        self::assertNotNull($summary);
        self::assertSame(['Juniors', 'Room 1', 1, 1, 0, 3], [$summary->className, $summary->roomName, $summary->totalSessions, $summary->plannedSessions, $summary->cancelledSessions, $summary->occurrences]);
        self::assertSame(1, $store->list(10, 0)->total);
        self::assertSame([], $store->list(10, 5)->series);
        self::assertSame('PLANNED', $store->sessions($series->id)[0]->status->value);
        self::assertNull($store->summary(Uuid::v4()->toRfc4122()));
    }

    public function testClashesCoverTheClassOrTheRoomAndIgnoreCancelledSessions(): void
    {
        $store = new DbalSessionSeriesStore($this->connection);
        $session = new DbalSessionStore($this->connection)->ministrySession($this->source);
        self::assertNotNull($session);
        $from = new DateTimeImmutable('2030-06-02T09:00:00Z');
        $until = new DateTimeImmutable('2030-06-02T09:30:00Z');

        self::assertTrue($store->conflicts($session->classId, Uuid::v4()->toRfc4122(), $from, $until), 'same class');
        self::assertTrue($store->conflicts(Uuid::v4()->toRfc4122(), $session->roomId, $from, $until), 'same room');
        self::assertFalse($store->conflicts($session->classId, $session->roomId, new DateTimeImmutable('2030-06-02T10:00:00Z'), new DateTimeImmutable('2030-06-02T11:00:00Z')), 'half-open: ends exactly when the next starts');
        $this->connection->executeStatement("UPDATE children_sessions SET status = 'CANCELLED' WHERE id = ?", [$this->source]);
        self::assertFalse($store->conflicts($session->classId, $session->roomId, $from, $until));
    }

    public function testTheSchedulingGuardBlocksAnotherConnectionUntilTheHolderFinishes(): void
    {
        $other = DriverManager::getConnection(new DsnParser(['postgresql' => 'pdo_pgsql'])->parse((string) getenv('DATABASE_URL')));
        try {
            $this->connection->beginTransaction();
            new DbalSessionSeriesStore($this->connection)->lockScheduling();

            $other->beginTransaction();
            $other->executeStatement("SET LOCAL lock_timeout = '300ms'");
            try {
                new DbalSessionSeriesStore($other)->lockScheduling();
                self::fail('A second planner must wait for the first.');
            } catch (DriverException $timeout) {
                self::assertSame('55P03', $timeout->getSQLState());
            }
            $other->rollBack();

            $this->connection->rollBack();
            $other->beginTransaction();
            new DbalSessionSeriesStore($other)->lockScheduling();
            $other->rollBack();
        } finally {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            if ($other->isTransactionActive()) {
                $other->rollBack();
            }
            $other->close();
        }
    }
}
