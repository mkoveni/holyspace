<?php

declare(strict_types=1);

namespace App\Tests\Performance;

use App\ChildrenMinistry\Domain\Model\ChildCheckIn;
use App\ChildrenMinistry\Domain\Model\MinistrySession;
use App\ChildrenMinistry\Domain\Repository\ChildrenMinistryRepository;
use App\ChildrenMinistry\Infrastructure\ReadModel\MinistryAttendanceDirectory;
use App\Events\Application\ReadModel\OccurrenceDirectory;
use App\Events\Domain\Repository\EventRegistrationRepository;
use App\Events\Infrastructure\ReadModel\DbalMemberBookings;
use App\Events\Infrastructure\ReadModel\DbalOccurrenceDirectory;
use App\People\Infrastructure\ReadModel\DBAL\DbalPersonDirectory;
use App\Shared\Application\WorkflowTransaction;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class ReadModelQueryTest extends TestCase
{
    private Connection $db;
    private QueryCounter $queries;

    protected function setUp(): void
    {
        $this->queries = new QueryCounter();
        $config = new Configuration();
        $config->setMiddlewares([new Middleware($this->queries)]);
        $this->db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $this->db->executeStatement('CREATE TABLE people_persons (id TEXT, first_name TEXT, last_name TEXT, date_of_birth TEXT, email TEXT, phone TEXT)');
        $this->db->executeStatement('CREATE TABLE church_events (id TEXT, title TEXT, timezone TEXT)');
        $this->db->executeStatement('CREATE TABLE church_event_occurrences (id TEXT, event_id TEXT, starts_at TEXT, ends_at TEXT, status TEXT, location TEXT)');
        $this->db->executeStatement('CREATE TABLE event_registrations (id TEXT, person_id TEXT, occurrence_id TEXT, status TEXT, version INTEGER, queue_token TEXT)');
        $this->queries->count = 0;
    }

    public function testPeopleAreLoadedInBatchesWithMissingAndDuplicateIds(): void
    {
        $ids = [];
        for ($i = 0; $i < 501; ++$i) {
            $id = Uuid::v7()->toRfc4122();
            $ids[] = $id;
            $this->db->insert('people_persons', ['id' => $id, 'first_name' => 'Member', 'last_name' => (string) $i]);
        }
        $missing = Uuid::v7()->toRfc4122();
        $this->queries->count = 0;
        $directory = new DbalPersonDirectory($this->db);
        $people = $directory->getMany([...$ids, $ids[0], $missing]);
        self::assertCount(501, $people);
        self::assertSame('Member 0', $people[$ids[0]]['name']);
        self::assertArrayNotHasKey($missing, $people);
        self::assertSame(2, $this->queries->count);
        $this->queries->count = 0;
        self::assertSame([], $directory->getMany([]));
        self::assertSame(0, $this->queries->count);
        self::assertSame($people[$ids[0]], $directory->get($ids[0]));
    }

    public function testOccurrenceBatchPreservesTheSingleReadContract(): void
    {
        $eventId = Uuid::v7()->toRfc4122();
        $this->db->insert('church_events', ['id' => $eventId, 'title' => 'Service', 'timezone' => 'Africa/Johannesburg']);
        $ids = [];
        for ($i = 0; $i < 25; ++$i) {
            $ids[] = $id = Uuid::v7()->toRfc4122();
            $this->db->insert('church_event_occurrences', ['id' => $id, 'event_id' => $eventId, 'starts_at' => '2026-10-25T08:00:00+02:00', 'ends_at' => '2026-10-25T10:00:00+02:00', 'status' => 'scheduled', 'location' => 'Hall']);
        }
        $this->queries->count = 0;
        $directory = new DbalOccurrenceDirectory($this->db);
        $items = $directory->getMany([...$ids, $ids[0]]);
        self::assertCount(25, $items);
        self::assertSame(1, $this->queries->count);
        self::assertSame($items[$ids[0]], $directory->get($ids[0]));
        self::assertNull($directory->get(Uuid::v7()->toRfc4122()));
    }

    public function testMemberBookingListUsesOneQueryAndKeepsMissingOccurrences(): void
    {
        $personId = Uuid::v7()->toRfc4122();
        $eventId = Uuid::v7()->toRfc4122();
        $occurrenceId = Uuid::v7()->toRfc4122();
        $this->db->insert('church_events', ['id' => $eventId, 'title' => 'Service', 'timezone' => 'UTC']);
        $this->db->insert('church_event_occurrences', ['id' => $occurrenceId, 'event_id' => $eventId, 'status' => 'scheduled']);
        for ($i = 0; $i < 105; ++$i) {
            $this->db->insert('event_registrations', ['id' => (string) $i, 'person_id' => $personId, 'occurrence_id' => 104 === $i ? 'missing' : $occurrenceId, 'status' => 'confirmed', 'version' => 3, 'queue_token' => sprintf('%03d', $i)]);
        }
        $records = $this->createMock(EventRegistrationRepository::class);
        $records->expects(self::never())->method('find');
        $occurrences = $this->createMock(OccurrenceDirectory::class);
        $occurrences->expects(self::never())->method('get');
        $transactions = $this->createStub(WorkflowTransaction::class);
        $registration = new \App\Events\Application\Command\CancelEventRegistration\CancelEventRegistrationHandler($records, $occurrences, $transactions, new MockClock());
        $read = new DbalMemberBookings($this->db, $records, $occurrences, $transactions, $registration);
        $this->queries->count = 0;
        $items = $read->list($personId);
        self::assertSame(1, $this->queries->count);
        self::assertCount(100, $items);
        self::assertSame('104', $items[0]['id']);
        self::assertNull($items[0]['occurrence']);
        self::assertSame('Service', $items[1]['occurrence']['title']);
        self::assertSame(3, $items[1]['version']);
        self::assertSame([], $read->list(Uuid::v7()->toRfc4122()));
    }

    public function testChildrenAttendanceLoadsEachCollectionOnceAndDeduplicatesChildren(): void
    {
        $occurrence = Uuid::v7()->toRfc4122();
        $child = Uuid::v7()->toRfc4122();
        $staff = Uuid::v7()->toRfc4122();
        $sessions = [];
        $checkins = [];
        for ($i = 0; $i < 20; ++$i) {
            $session = new MinistrySession(Uuid::v7()->toRfc4122(), $occurrence, [$staff]);
            $sessions[] = $session;
            $checkins[] = new ChildCheckIn($session->id, $child, '2026-10-25T08:00:00+02:00', $staff);
        }
        $checkins[] = new ChildCheckIn(Uuid::v7()->toRfc4122(), $child, '2026-10-25T08:00:00+02:00', $staff);
        $records = $this->createMock(ChildrenMinistryRepository::class);
        $records->expects(self::exactly(2))->method('all')->willReturnMap([['sessions', $sessions], ['checkins', $checkins]]);
        $items = (new MinistryAttendanceDirectory($records))->all();
        self::assertCount(1, $items);
        self::assertSame($occurrence.':'.$child, $items[0]['id']);
        self::assertSame('present', $items[0]['status']);
    }

    public function testVolunteerEligibilityUsesFixedReadsAndStillRejectsExpiredScreening(): void
    {
        $person = Uuid::v7()->toRfc4122();
        $occurrenceId = Uuid::v7()->toRfc4122();
        $role = new \App\Volunteering\Domain\Model\VolunteerRole('Supervisor', true);
        $shift = new \App\Volunteering\Domain\Model\ShiftRequirement($occurrenceId, $role->id, '2026-10-25T08:00:00+02:00', '2026-10-25T10:00:00+02:00', 30);
        $assignments = [];
        for ($i = 0; $i < 20; ++$i) {
            $assignments[] = new \App\Volunteering\Domain\Model\VolunteerAssignment($shift->id, $person);
        }
        foreach (['2026-10-24' => false, '2026-10-26' => true] as $expiry => $expected) {
            $profile = new \App\Volunteering\Domain\Model\VolunteerProfile($person, [$role->id], [['startsAt' => $shift->startsAt, 'endsAt' => $shift->endsAt]], screeningValidUntil: $expiry);
            $records = $this->createMock(\App\Volunteering\Domain\Repository\VolunteeringRepository::class);
            $records->expects(self::exactly(4))->method('all')->willReturnMap([['shifts', [$shift]], ['roles', [$role]], ['profiles', [$profile]], ['assignments', $assignments]]);
            $records->expects(self::never())->method('find');
            $occurrences = $this->createStub(OccurrenceDirectory::class);
            $occurrences->method('get')->willReturn(['status' => 'scheduled', 'startsAt' => $shift->startsAt, 'endsAt' => $shift->endsAt]);
            $directory = new \App\Volunteering\Infrastructure\ReadModel\VolunteerStaffingDirectory($records, $occurrences);
            self::assertSame($expected, $directory->eligibleForOccurrence($person, $occurrenceId));
        }
    }

    public function testAssigneePermissionsAreEvaluatedWithoutPerAccountQueries(): void
    {
        $this->db->executeStatement('CREATE TABLE user_accounts (id TEXT, username TEXT, status TEXT)');
        $this->db->executeStatement('CREATE TABLE user_roles (user_id TEXT, role_id TEXT)');
        $this->db->executeStatement('CREATE TABLE role_permissions (role_id TEXT, permission_id TEXT)');
        $this->db->executeStatement('CREATE TABLE permissions (id TEXT, code TEXT)');
        foreach (['manager' => 'active', 'worker' => 'active', 'disabled' => 'disabled'] as $id => $status) {
            $this->db->insert('user_accounts', ['id' => $id, 'username' => ucfirst($id), 'status' => $status]);
            $this->db->insert('user_roles', ['user_id' => $id, 'role_id' => $id]);
        }
        foreach (['manage' => 'followups.manage', 'read' => 'people.manage', 'work' => 'followups.work'] as $id => $code) {
            $this->db->insert('permissions', ['id' => $id, 'code' => $code]);
        }
        foreach (['manager' => ['manage', 'read'], 'worker' => ['work'], 'disabled' => ['manage', 'read']] as $role => $permissions) {
            foreach ($permissions as $permission) {
                $this->db->insert('role_permissions', ['role_id' => $role, 'permission_id' => $permission]);
            }
        }
        $permissions = $this->createMock(\App\IdentityAccess\Application\ReadModel\IdentityAccessReadRepository::class);
        $permissions->expects(self::never())->method('permissionCodesForUser');
        $accounts = new \App\Reporting\Infrastructure\ReadModel\DbalFollowupAccounts($this->db, $permissions);
        $this->queries->count = 0;
        self::assertSame([['id' => 'manager', 'username' => 'Manager']], $accounts->assignees(['people.read']));
        self::assertSame(1, $this->queries->count);
        self::assertSame(['manager' => 'Manager', 'worker' => 'Worker'], $accounts->names(['manager', 'worker', 'missing', 'manager']));
        self::assertSame(2, $this->queries->count);
    }
}
final class QueryCounter extends AbstractLogger
{
    public int $count = 0;

    public function log($level, string|Stringable $message, array $context = []): void
    {
        if (isset($context['sql'])) {
            ++$this->count;
        }
    }
}
