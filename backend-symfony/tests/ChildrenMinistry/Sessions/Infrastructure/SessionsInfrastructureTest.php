<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Sessions\Infrastructure;

use App\ChildrenMinistry\Participation\Application\Contract\ParticipationReader;
use App\ChildrenMinistry\Sessions\Domain\Attendance;
use App\ChildrenMinistry\Sessions\Domain\MinistrySession;
use App\ChildrenMinistry\Sessions\Domain\Repository\SessionRepository;
use App\ChildrenMinistry\Sessions\Domain\SessionStaff;
use App\ChildrenMinistry\Sessions\Domain\SessionStatus;
use App\ChildrenMinistry\Sessions\Domain\Worker;
use App\ChildrenMinistry\Sessions\Infrastructure\Persistence\DbalAttendanceStore;
use App\ChildrenMinistry\Sessions\Infrastructure\Persistence\DbalSessionStore;
use App\ChildrenMinistry\Sessions\Infrastructure\ServingActivityGuard;
use App\ChildrenMinistry\Sessions\Infrastructure\ServingSessionActivities;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\Tests\ChildrenMinistry\Participation\Presentation\ChildrenApiTestCase;
use App\Volunteering\Application\ActivityRegistry;
use App\Volunteering\Application\Contract\ServingActivity;
use App\Volunteering\Application\Contract\ShiftView;
use App\Volunteering\Domain\VolunteerException;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Tools\DsnParser;
use Symfony\Component\Uid\Uuid;

/** The adapters Volunteering uses to staff sessions: the published activity source and the row locks. */
final class SessionsInfrastructureTest extends ChildrenApiTestCase
{
    private string $group;
    private string $room;

    protected function setUp(): void
    {
        parent::setUp();
        $location = $this->create('/locations', ['name' => 'Main', 'timeZone' => 'Africa/Johannesburg']);
        $this->room = $this->create('/rooms', ['locationId' => $location, 'name' => 'Room 1', 'capacity' => 12]);
        $this->group = $this->create('/classes', ['programmeId' => $this->programme(), 'name' => 'Juniors', 'minAgeMonths' => 60, 'maxAgeMonths' => 156]);
    }

    private function session(string $start, string $end): string
    {
        return $this->create('/sessions', ['classId' => $this->group, 'roomId' => $this->room, 'startsAt' => $start, 'endsAt' => $end, 'seatLimit' => 10]);
    }

    private function activities(): ServingSessionActivities
    {
        $reader = self::getContainer()->get(ParticipationReader::class);
        \assert($reader instanceof ParticipationReader);

        return new ServingSessionActivities(new DbalSessionStore($this->connection), $reader);
    }

    public function testSessionsArePublishedAsChildrenSessionActivities(): void
    {
        $id = $this->session('2030-06-01T09:00:00Z', '2030-06-01T11:00:00Z');

        $source = $this->activities();
        $activity = $source->find($id);

        self::assertSame('CHILDREN_SESSION', $source->kind());
        self::assertNotNull($activity);
        self::assertSame([$id, 'Juniors', true, null, null], [$activity->id, $activity->label, $activity->active, $activity->scheduleRevision, $activity->venueLabel]);
        self::assertSame(['2030-06-01T09:00:00Z', '2030-06-01T11:00:00Z'], [$activity->startsAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'), $activity->endsAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z')]);
        self::assertNull($source->find('00000000-0000-4000-8000-000000000000'));
    }

    public function testClosedAndCancelledSessionsAreInactiveAndLeaveTheUpcomingList(): void
    {
        $open = $this->session('2030-06-01T09:00:00Z', '2030-06-01T11:00:00Z');
        $cancelled = $this->session('2030-06-02T09:00:00Z', '2030-06-02T11:00:00Z');
        $closed = $this->session('2030-06-03T09:00:00Z', '2030-06-03T11:00:00Z');
        $later = $this->session('2030-06-04T09:00:00Z', '2030-06-04T11:00:00Z');
        $this->call('PUT', '/api/children/sessions/status', $this->admin, ['sessionId' => $cancelled, 'expectedVersion' => 0, 'status' => 'CANCELLED', 'reason' => 'Rain']);
        $this->call('PUT', '/api/children/sessions/status', $this->admin, ['sessionId' => $closed, 'expectedVersion' => 0, 'status' => 'CLOSED', 'reason' => 'Done']);
        $source = $this->activities();
        $from = new DateTimeImmutable('2030-05-31T00:00:00Z');
        $until = new DateTimeImmutable('2030-06-10T00:00:00Z');

        foreach ([$cancelled, $closed] as $inactive) {
            $activity = $source->find($inactive);
            self::assertNotNull($activity);
            self::assertFalse($activity->active);
        }
        self::assertSame([$open, $later], array_map(static fn ($activity): string => $activity->id, $source->upcoming($from, $until)));
        self::assertSame([$later], array_map(static fn ($activity): string => $activity->id, $source->upcoming($from, $until, 1, 1)));
        self::assertSame([], $source->upcoming($from, $until, 5, 2));
    }

    public function testVolunteeringResolvesAndFingerprintsASessionThroughItsRegistry(): void
    {
        $id = $this->session('2030-06-01T09:00:00Z', '2030-06-01T11:00:00Z');
        $registry = self::getContainer()->get(ActivityRegistry::class);
        \assert($registry instanceof ActivityRegistry, 'the tagged CHILDREN_SESSION source is registered');
        $shift = static fn (?string $revision): ShiftView => new ShiftView('s', 't', 'r', 'CHILDREN_SESSION', $id, $revision, new DateTimeImmutable('2030-06-01T09:00:00Z'), new DateTimeImmutable('2030-06-01T11:00:00Z'), 'OPEN', 1);

        $revision = $registry->revision($shift(null));
        self::assertNotNull($revision);
        $registry->require($shift($revision));

        $this->connection->executeStatement("UPDATE children_sessions SET ends_at = '2030-06-01 12:00:00+00' WHERE id = ?", [$id]);
        try {
            $registry->require($shift($revision));
            self::fail('A rescheduled session must invalidate the shift.');
        } catch (VolunteerException $error) {
            self::assertSame('The linked activity was rescheduled. Review the duty and update the shift before proceeding.', $error->getMessage());
        }

        $this->call('PUT', '/api/children/sessions/status', $this->admin, ['sessionId' => $id, 'expectedVersion' => 0, 'status' => 'CANCELLED', 'reason' => 'Rain']);
        $this->expectException(VolunteerException::class);
        $this->expectExceptionMessage('The linked activity is cancelled, closed or unavailable.');
        $registry->revision($shift(null));
    }

    public function testTheGuardLocksSessionsOfItsKindInIdOrderAndIgnoresOthers(): void
    {
        $log = [];
        $recording = new class ($log) implements SessionRepository {
            /** @param list<string> $log */
            public function __construct(public array &$log)
            {
            }

            public function ministrySessionForUpdate(string $id): ?MinistrySession
            {
                $this->log[] = $id;

                return null;
            }

            public function insertMinistrySession(MinistrySession $session): void
            {
            }

            public function updateMinistrySession(MinistrySession $session): void
            {
            }

            public function workerForUpdate(string $personId): ?Worker
            {
                return null;
            }

            public function insertWorker(Worker $worker): void
            {
            }

            public function updateWorker(Worker $worker): void
            {
            }

            public function saveSessionStaff(SessionStaff $staff): void
            {
            }
        };

        new ServingActivityGuard($recording)->lock([
            new ServingActivity('CHILDREN_SESSION', 'b'),
            new ServingActivity('EVENT', 'a'),
            new ServingActivity('CHILDREN_SESSION', 'a'),
            new ServingActivity('CHILDREN_SESSION', 'b'),
            new ServingActivity('CHILDREN_SESSION', 'B'),
        ]);

        self::assertSame(['B', 'a', 'b'], $log, 'unique, byte order, only this kind');
    }

    public function testTheGuardReallyBlocksAnotherConnectionUntilTheHolderFinishes(): void
    {
        $id = $this->session('2030-06-01T09:00:00Z', '2030-06-01T11:00:00Z');
        $guard = new ServingActivityGuard(new DbalSessionStore($this->connection));
        $other = DriverManager::getConnection(new DsnParser(['postgresql' => 'pdo_pgsql'])->parse((string) getenv('DATABASE_URL')));
        try {
            $this->connection->beginTransaction();
            $guard->lock([new ServingActivity('CHILDREN_SESSION', $id)]);

            $other->executeStatement("SET lock_timeout = '300ms'");
            try {
                new DbalSessionStore($other)->ministrySessionForUpdate($id);
                self::fail('A second transaction must wait for the first to release the session.');
            } catch (DriverException $timeout) {
                self::assertSame('55P03', $timeout->getSQLState());
            }
            $this->connection->rollBack();
            self::assertNotNull(new DbalSessionStore($other)->ministrySessionForUpdate($id));
        } finally {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            $other->close();
        }
    }

    public function testAStaleUpdateIsAConflictAndChangesNothing(): void
    {
        $id = $this->session('2030-06-01T09:00:00Z', '2030-06-01T11:00:00Z');
        $store = new DbalSessionStore($this->connection);
        $session = $store->ministrySession($id);
        self::assertNotNull($session);

        $store->updateMinistrySession($session->withStatus(SessionStatus::Cancelled, 0));
        try {
            $store->updateMinistrySession($session->withStatus(SessionStatus::Open, 0));
            self::fail('Expected a conflict.');
        } catch (ChildrenException $error) {
            self::assertSame('Record changed. Reload and try again.', $error->getMessage());
        }

        self::assertSame(SessionStatus::Cancelled, $store->ministrySession($id)?->status);
    }

    public function testTwoSimultaneousCheckInsOfTheSameChildLeaveExactlyOneOpenVisit(): void
    {
        $session = $this->session('2030-06-01T09:00:00Z', '2030-06-01T11:00:00Z');
        $child = $this->person('Little', $this->yearsAgo(8));
        $visit = static fn (string $id): Attendance => Attendance::arrive($id, $child, $session, new DateTimeImmutable('2030-06-01T09:05:00Z'), Uuid::v4()->toRfc4122(), Uuid::v4()->toRfc4122());
        $other = DriverManager::getConnection(new DsnParser(['postgresql' => 'pdo_pgsql'])->parse((string) getenv('DATABASE_URL')));
        try {
            $this->connection->beginTransaction();
            new DbalAttendanceStore($this->connection)->insertAttendance($visit(Uuid::v4()->toRfc4122()));

            $other->beginTransaction();
            $other->executeStatement("SET LOCAL lock_timeout = '300ms'");
            try {
                new DbalAttendanceStore($other)->insertAttendance($visit(Uuid::v4()->toRfc4122()));
                self::fail('The second check-in must wait for the first to finish.');
            } catch (DriverException $timeout) {
                self::assertSame('55P03', $timeout->getSQLState(), 'it queued behind the first check-in');
            }
            $other->rollBack();

            $this->connection->commit();
            $other->beginTransaction();
            try {
                new DbalAttendanceStore($other)->insertAttendance($visit(Uuid::v4()->toRfc4122()));
                self::fail('Once the first has committed the second must be refused.');
            } catch (UniqueConstraintViolationException) {
                $other->rollBack();
            }
        } finally {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            $other->close();
        }

        self::assertSame(1, $this->rows('children_attendance', "child_id = '$child' AND checked_out_at IS NULL"));
    }
}
