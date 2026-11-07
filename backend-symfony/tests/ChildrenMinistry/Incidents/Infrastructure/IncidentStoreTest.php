<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Incidents\Infrastructure;

use App\ChildrenMinistry\Incidents\Domain\Incident;
use App\ChildrenMinistry\Incidents\Domain\IncidentDetails;
use App\ChildrenMinistry\Incidents\Domain\IncidentEventType;
use App\ChildrenMinistry\Incidents\Infrastructure\Persistence\DbalIncidentStore;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\Shared\Domain\CalendarDate;
use App\Tests\ChildrenMinistry\Participation\Presentation\ChildrenApiTestCase;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Tools\DsnParser;
use Symfony\Component\Uid\Uuid;

final class IncidentStoreTest extends ChildrenApiTestCase
{
    private string $id;

    protected function setUp(): void
    {
        parent::setUp();
        $group = $this->create('/classes', ['programmeId' => $this->programme(), 'name' => 'Juniors', 'minAgeMonths' => 60, 'maxAgeMonths' => 156]);
        $location = $this->create('/locations', ['name' => 'Main', 'timeZone' => 'Africa/Johannesburg']);
        $room = $this->create('/rooms', ['locationId' => $location, 'name' => 'Room 1', 'capacity' => 12]);
        $session = $this->create('/sessions', ['classId' => $group, 'roomId' => $room, 'startsAt' => '2030-06-01T09:00:00Z', 'endsAt' => '2030-06-01T11:00:00Z', 'seatLimit' => 4]);
        $programme = (string) $this->connection->fetchOne('SELECT programme_id FROM children_classes WHERE id = ?', [$group]);
        $policy = $this->create('/consent-policies', ['programmeId' => $programme, 'expectedVersion' => 0, 'label' => 'v1', 'documentReference' => 'docs/v1', 'contentHash' => str_repeat('a', 64)]);
        $guardian = $this->person('Parent', $this->yearsAgo(35));
        $child = $this->person('Child', $this->yearsAgo(8));
        $at = static fn (string $modify): string => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify($modify)->format('Y-m-d\TH:i:s.u\Z');
        $authority = $this->create('/guardian-authorities', ['childId' => $child, 'guardianId' => $guardian, 'validFrom' => $at('-1 day'), 'validUntil' => $at('+1 year'), 'evidenceReference' => 'e']);
        $consent = $this->create('/consents', ['childId' => $child, 'programmeId' => $programme, 'policyId' => $policy, 'authorityId' => $authority, 'validFrom' => $at('-1 hour'), 'validUntil' => $at('+6 months'), 'acceptedAt' => $at('+0 seconds'), 'evidenceReference' => 'e']);
        $enrolment = $this->enrol($programme, $child);
        $assignment = $this->create('/class-assignments', ['enrolmentId' => $enrolment, 'classId' => $group, 'validFrom' => $this->today('-1 day'), 'validUntil' => $this->today('+1 month')]);
        $visit = Uuid::v4()->toRfc4122();
        $segment = Uuid::v4()->toRfc4122();
        $this->connection->executeStatement("INSERT INTO children_attendance (id, child_id, session_id, arrived_at, arrived_by, arrival_request_id) VALUES (?, ?, ?, '2030-06-01 09:05:00+00', ?, ?)", [$visit, $child, $session, $this->adminId, Uuid::v4()->toRfc4122()]);
        $this->connection->executeStatement('INSERT INTO children_attendance_segments (id, attendance_id, session_id, enrolment_id, assignment_id, consent_id, arrived_at) VALUES (?, ?, ?, ?, ?, ?, ?)', [$segment, $visit, $session, $enrolment, $assignment, $consent, '2030-06-01 09:05:00+00']);

        $incident = Incident::report(Uuid::v4()->toRfc4122(), $child, $session, $segment, 'INJURY', 'LOW', new DateTimeImmutable('2030-06-01T09:30:00Z'), $this->adminId, new DateTimeImmutable('2030-06-01T09:31:00Z'), CalendarDate::parse('2030-06-02'), CalendarDate::parse('2031-06-01'));
        $this->id = $incident->id;
        new DbalIncidentStore($this->connection)->insert($incident, new IncidentDetails('Private', 'Private'), Uuid::v4()->toRfc4122(), str_repeat('b', 64));
    }

    public function testAStaleUpdateIsAConflictAndChangesNothing(): void
    {
        $store = new DbalIncidentStore($this->connection);
        $incident = $store->lock($this->id);
        self::assertNotNull($incident);
        $store->update($incident->triage(0, $this->adminId, CalendarDate::parse('2030-06-03'), CalendarDate::parse('2030-06-01')));

        try {
            $store->update($incident->progress(0, IncidentEventType::Action, CalendarDate::parse('2030-06-01')));
            self::fail('Expected a conflict.');
        } catch (ChildrenException $conflict) {
            self::assertSame('Record changed. Reload and try again.', $conflict->getMessage());
        }
        self::assertSame(1, $store->lock($this->id)?->version);
    }

    public function testTheIncidentRowLockMakesAConcurrentFollowUpWait(): void
    {
        $other = DriverManager::getConnection(new DsnParser(['postgresql' => 'pdo_pgsql'])->parse((string) getenv('DATABASE_URL')));
        try {
            $this->connection->beginTransaction();
            self::assertNotNull(new DbalIncidentStore($this->connection)->lock($this->id));

            $other->beginTransaction();
            $other->executeStatement("SET LOCAL lock_timeout = '300ms'");
            try {
                new DbalIncidentStore($other)->lock($this->id);
                self::fail('A second follow-up must wait for the first.');
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

    public function testTheSameRequestIdCannotFileTwoIncidents(): void
    {
        $this->expectException(UniqueConstraintViolationException::class);
        $this->connection->executeStatement(
            'INSERT INTO children_incidents (id, child_id, session_id, segment_id, category, severity, occurred_at, reported_by, reported_at, request_id, request_hash, status, review_on, retain_until) SELECT ?, child_id, session_id, segment_id, category, severity, occurred_at, reported_by, reported_at, request_id, request_hash, status, review_on, retain_until FROM children_incidents WHERE id = ?',
            [Uuid::v4()->toRfc4122(), $this->id],
        );
    }

    public function testTheSessionCountsFeedTheSessionsAdapter(): void
    {
        $store = new DbalIncidentStore($this->connection);
        $session = (string) $this->connection->fetchOne('SELECT session_id FROM children_incidents WHERE id = ?', [$this->id]);

        $counts = $store->counts($session, CalendarDate::parse('2030-06-02'));

        self::assertSame([1, 1, 1], [$counts->unresolved, $counts->overdue, $counts->unassigned]);
        self::assertSame(1, $store->unresolved($session, CalendarDate::parse('2030-06-02')));
        self::assertSame(0, $store->unresolved($session, CalendarDate::parse('2031-06-02')), 'past retention');
        self::assertSame(0, $store->counts(Uuid::v4()->toRfc4122(), CalendarDate::parse('2030-06-02'))->unresolved);
    }
}
