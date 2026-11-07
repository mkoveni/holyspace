<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Sessions\Infrastructure;

use App\ChildrenMinistry\Sessions\Application\PlanningSession;
use App\ChildrenMinistry\Sessions\Infrastructure\Persistence\DbalSessionStore;
use App\ChildrenMinistry\Sessions\Infrastructure\Persistence\DbalTeamPlanningStore;
use App\Tests\ChildrenMinistry\Participation\Presentation\ChildrenApiTestCase;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Tools\DsnParser;
use Symfony\Component\Uid\Uuid;

final class TeamPlanningStoreTest extends ChildrenApiTestCase
{
    private string $room;
    private string $group;
    private string $worker;

    protected function setUp(): void
    {
        parent::setUp();
        $location = $this->create('/locations', ['name' => 'Main', 'timeZone' => 'Africa/Johannesburg']);
        $this->room = $this->create('/rooms', ['locationId' => $location, 'name' => 'Room 1', 'capacity' => 12]);
        $this->group = $this->create('/classes', ['programmeId' => $this->programme(), 'name' => 'Juniors', 'minAgeMonths' => 60, 'maxAgeMonths' => 156]);
        $person = $this->person('Mabasa', $this->yearsAgo(30));
        $account = $this->accountWith('mabasa', ['children.station']);
        $until = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+20 years')->format('Y-m-d\TH:i:s\Z');
        $this->call('POST', '/api/children/workers', $this->admin, ['personId' => $person, 'accountId' => $account, 'screenedUntil' => $until, 'trainedUntil' => $until, 'evidenceReference' => 'Verified']);
        $this->worker = $person;
    }

    private function session(string $start, string $end, ?string $room = null, ?string $class = null): string
    {
        return $this->create('/sessions', ['classId' => $class ?? $this->group, 'roomId' => $room ?? $this->room, 'startsAt' => $start, 'endsAt' => $end, 'seatLimit' => 4]);
    }

    private function assign(string $session): void
    {
        $this->connection->executeStatement("INSERT INTO children_session_staff (session_id, worker_id, role, present) VALUES (?, ?, 'LEADER', FALSE)", [$session, $this->worker]);
    }

    private function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time);
    }

    public function testUpcomingListsOnlyFutureOpenSessionsInTheWindowWithTheirVenueFacts(): void
    {
        $in = $this->session('2030-06-10T09:00:00Z', '2030-06-10T10:00:00Z');
        $this->session('2030-06-01T09:00:00Z', '2030-06-01T10:00:00Z', $this->create('/rooms', ['locationId' => $this->connection->fetchOne('SELECT location_id FROM children_rooms LIMIT 1'), 'name' => 'B', 'capacity' => 5]), $this->create('/classes', ['programmeId' => $this->connection->fetchOne('SELECT programme_id FROM children_classes LIMIT 1'), 'name' => 'Other', 'minAgeMonths' => 1, 'maxAgeMonths' => 2]));
        $cancelled = $this->session('2030-06-12T09:00:00Z', '2030-06-12T10:00:00Z');
        $this->connection->executeStatement("UPDATE children_sessions SET status = 'CANCELLED' WHERE id = ?", [$cancelled]);

        $rows = new DbalTeamPlanningStore($this->connection)->upcoming($this->at('2030-06-05T00:00:00Z'), $this->at('2030-06-30T00:00:00Z'), null, null, $this->at('2030-06-02T00:00:00Z'));

        self::assertSame([$in], array_map(static fn (PlanningSession $row): string => $row->value->id, $rows));
        self::assertSame(['Juniors', 'Room 1', 'Africa/Johannesburg', 12, 1, 2], [$rows[0]->ministryClass, $rows[0]->room, $rows[0]->timeZone, $rows[0]->roomCapacity, $rows[0]->minimumStaff, $rows[0]->childrenPerStaff]);
        $all = new DbalTeamPlanningStore($this->connection)->upcoming($this->at('2030-05-01T00:00:00Z'), $this->at('2030-06-30T00:00:00Z'), null, null, $this->at('2030-06-02T00:00:00Z'));
        self::assertSame([$in], array_map(static fn (PlanningSession $row): string => $row->value->id, $all), 'sessions that already started (or are cancelled) are not plannable');
        self::assertSame([], new DbalTeamPlanningStore($this->connection)->upcoming($this->at('2030-06-05T00:00:00Z'), $this->at('2030-06-30T00:00:00Z'), Uuid::v4()->toRfc4122(), null, $this->at('2030-06-02T00:00:00Z')));
        self::assertSame([], new DbalTeamPlanningStore($this->connection)->upcoming($this->at('2030-06-05T00:00:00Z'), $this->at('2030-06-30T00:00:00Z'), null, Uuid::v4()->toRfc4122(), $this->at('2030-06-02T00:00:00Z')));
    }

    public function testFactsReturnTheSelectedTeamsAndOnlyOpenOverlappingBookings(): void
    {
        $selected = $this->session('2030-06-10T09:00:00Z', '2030-06-10T10:00:00Z');
        $otherRoom = $this->create('/rooms', ['locationId' => $this->connection->fetchOne('SELECT location_id FROM children_rooms LIMIT 1'), 'name' => 'B', 'capacity' => 5]);
        $otherClass = $this->create('/classes', ['programmeId' => $this->connection->fetchOne('SELECT programme_id FROM children_classes LIMIT 1'), 'name' => 'Other', 'minAgeMonths' => 1, 'maxAgeMonths' => 2]);
        $overlap = $this->session('2030-06-10T09:30:00Z', '2030-06-10T10:30:00Z', $otherRoom, $otherClass);
        $closed = $this->session('2030-06-10T09:15:00Z', '2030-06-10T09:45:00Z', $this->create('/rooms', ['locationId' => $this->connection->fetchOne('SELECT location_id FROM children_rooms LIMIT 1'), 'name' => 'C', 'capacity' => 5]), $this->create('/classes', ['programmeId' => $this->connection->fetchOne('SELECT programme_id FROM children_classes LIMIT 1'), 'name' => 'Third', 'minAgeMonths' => 1, 'maxAgeMonths' => 2]));
        $far = $this->session('2030-07-10T09:00:00Z', '2030-07-10T10:00:00Z', $otherRoom, $otherClass);
        foreach ([$selected, $overlap, $closed, $far] as $session) {
            $this->assign($session);
        }
        $this->connection->executeStatement("UPDATE children_sessions SET status = 'CLOSED' WHERE id = ?", [$closed]);
        $store = new DbalTeamPlanningStore($this->connection);

        $facts = $store->facts($store->sessions([$selected]), []);

        self::assertSame([$selected], array_map(static fn ($member): string => $member->sessionId, $facts->staff));
        self::assertSame([$this->worker], array_map(static fn ($worker): string => $worker->personId, $facts->workers));
        $booked = array_map(static fn ($booking): string => $booking->sessionId, $facts->bookings);
        sort($booked);
        $expected = [$selected, $overlap];
        sort($expected);
        self::assertSame($expected, $booked, 'closed and out-of-window sessions are not bookings');
        self::assertSame(0, \count($store->facts([], [$this->worker])->workers));
    }

    public function testExtraWorkersAreLoadedEvenWithoutAssignments(): void
    {
        $selected = $this->session('2030-06-10T09:00:00Z', '2030-06-10T10:00:00Z');
        $store = new DbalTeamPlanningStore($this->connection);

        $facts = $store->facts($store->sessions([$selected]), [$this->worker, Uuid::v4()->toRfc4122()]);

        self::assertSame([], $facts->staff);
        self::assertSame([$this->worker], array_map(static fn ($worker): string => $worker->personId, $facts->workers));
    }

    public function testWorkersCanBeFilteredByIdAndAnEmptyFilterMatchesNone(): void
    {
        $store = new DbalTeamPlanningStore($this->connection);

        self::assertSame(1, $store->workers(null, 10, 0)->total);
        self::assertSame(1, $store->workers([$this->worker, Uuid::v4()->toRfc4122()], 10, 0)->total);
        self::assertSame(0, $store->workers([], 10, 0)->total);
        self::assertSame([], $store->workers([Uuid::v4()->toRfc4122()], 10, 0)->values);
        self::assertSame([], $store->workers(null, 10, 5)->values);
        self::assertSame(1, $store->workers(null, 10, 5)->total);
    }

    public function testRemovingAnAssignmentDeletesOnlyThatWorkersRow(): void
    {
        $session = $this->session('2030-06-10T09:00:00Z', '2030-06-10T10:00:00Z');
        $this->assign($session);
        $other = $this->session('2030-06-17T09:00:00Z', '2030-06-17T10:00:00Z');
        $this->assign($other);

        new DbalTeamPlanningStore($this->connection)->removeAssignment($session, $this->worker);

        self::assertSame(0, $this->rows('children_session_staff', "session_id = '$session'"));
        self::assertSame(1, $this->rows('children_session_staff', "session_id = '$other'"));
    }

    public function testTheWorkerRowLockSerialisesConcurrentPlansForTheSameWorker(): void
    {
        $other = DriverManager::getConnection(new DsnParser(['postgresql' => 'pdo_pgsql'])->parse((string) getenv('DATABASE_URL')));
        try {
            $this->connection->beginTransaction();
            self::assertNotNull(new DbalSessionStore($this->connection)->workerForUpdate($this->worker));

            $other->beginTransaction();
            $other->executeStatement("SET LOCAL lock_timeout = '300ms'");
            try {
                new DbalSessionStore($other)->workerForUpdate($this->worker);
                self::fail('A second plan for the same worker must wait for the first.');
            } catch (DriverException $timeout) {
                self::assertSame('55P03', $timeout->getSQLState());
            }
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
