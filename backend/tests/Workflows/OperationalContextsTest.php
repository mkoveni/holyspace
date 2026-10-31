<?php

declare(strict_types=1);

namespace App\Tests\Workflows;

use App\People\Application\ReadModel\PersonDirectory;
use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use DomainException;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Uid\Uuid;

final class OperationalContextsTest extends WebTestCase
{
    private $client;
    private Connection $db;
    private array $ids;

    protected static function getKernelClass(): string
    {
        return WorkflowsTestKernel::class;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = self::createClient(['debug' => false]);
        $this->client->disableReboot();
        $this->db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        foreach (['reporting', 'serviceplanning', 'volunteering', 'childrenministry', 'giving', 'attendance', 'ministries', 'communications'] as $context) {
            $this->db->executeStatement('CREATE TABLE '.$context.'_records(id TEXT PRIMARY KEY,kind TEXT,payload TEXT,version INTEGER)');
        }
        $this->db->executeStatement('CREATE TABLE event_registration_settings(id TEXT PRIMARY KEY,payload TEXT,version INTEGER)');
        $this->db->executeStatement('CREATE TABLE event_registrations(id TEXT PRIMARY KEY,occurrence_id TEXT,person_id TEXT,status TEXT,queue_token TEXT,queue_position INTEGER,payload TEXT,version INTEGER,UNIQUE(occurrence_id,person_id))');
        $this->db->executeStatement('CREATE TABLE forms_records(id TEXT PRIMARY KEY,kind TEXT,payload TEXT,version INTEGER,form_id TEXT,public_token TEXT UNIQUE,submission_key TEXT,UNIQUE(form_id,submission_key))');
        $this->db->executeStatement('CREATE TABLE user_accounts(id TEXT PRIMARY KEY,username TEXT,status TEXT DEFAULT "active")');
        $this->db->executeStatement('CREATE TABLE user_roles(user_id TEXT,role_id TEXT)');
        $this->db->executeStatement('CREATE TABLE permissions(id TEXT,code TEXT)');
        $this->db->executeStatement('CREATE TABLE role_permissions(role_id TEXT,permission_id TEXT)');
        $this->db->executeStatement('CREATE TABLE workflow_audit(id TEXT PRIMARY KEY,context TEXT,record_id TEXT,kind TEXT,action TEXT,version INTEGER,actor_id TEXT,occurred_at TEXT,UNIQUE(context,record_id,version))');
        $this->db->executeStatement('CREATE TABLE integration_outbox(sequence_no INTEGER PRIMARY KEY AUTOINCREMENT,id TEXT UNIQUE,type TEXT,schema_version INTEGER,payload TEXT,created_at TEXT,processed_at TEXT)');
        $this->db->executeStatement('CREATE TABLE integration_deliveries(message_id TEXT,consumer TEXT,PRIMARY KEY(message_id,consumer))');
        $this->db->executeStatement('CREATE TABLE church_events(id TEXT PRIMARY KEY,title TEXT,type TEXT,description TEXT,timezone TEXT,recurrence TEXT,created_at TEXT,updated_at TEXT)');
        $this->db->executeStatement('CREATE TABLE church_event_occurrences(id TEXT PRIMARY KEY,event_id TEXT,starts_at TEXT,ends_at TEXT,location TEXT,status TEXT,created_at TEXT,updated_at TEXT)');
        $this->ids = array_combine(['adult', 'child', 'collector', 'otherChild'], array_map(fn () => Uuid::v7()->toRfc4122(), range(1, 4)));
        $directory = $this->createStub(PersonDirectory::class);
        $directory->method('get')->willReturnCallback(function ($id) {
            foreach ($this->ids as $kind => $personId) {
                if ($id === $personId) {
                    return ['id' => $id, 'name' => $kind, 'email' => 'person@example.test', 'phone' => '+27123456789', 'dateOfBirth' => in_array($kind, ['child', 'otherChild']) ? '2020-01-01' : '1980-01-01'];
                }
            }

            return null;
        });
        $directory->method('getMany')->willReturnCallback(static function (array $ids) use ($directory): array {
            $people = [];
            foreach ($ids as $id) {
                $person = $directory->get($id);
                if (null !== $person) {
                    $people[$id] = $person;
                }
            }

            return $people;
        });
        self::getContainer()->set(\Symfony\Component\Clock\ClockInterface::class, new \Symfony\Component\Clock\MockClock('2030-01-06T09:00:00Z'));
        self::getContainer()->set(\App\Communications\Application\Port\MessageGateway::class, new WorkflowTestMessageGateway());
        self::getContainer()->set(Connection::class, $this->db);
        self::getContainer()->set(PersonDirectory::class, $directory);
        $reportPeople = $this->createStub(\App\People\Application\ReadModel\PeopleReportDirectory::class);
        $reportPeople->method('all')->willReturnCallback(fn () => array_map(fn ($kind, $id) => ['id' => $id, 'name' => 'collector' === $kind ? '=FORMULA' : $kind, 'status' => 'collector' === $kind ? 'prospect' : 'active', 'dateOfBirth' => in_array($kind, ['child', 'otherChild']) ? '2020-01-01' : '1980-01-01'], array_keys($this->ids), array_values($this->ids)));
        self::getContainer()->set(\App\People\Application\ReadModel\PeopleReportDirectory::class, $reportPeople);
        self::getContainer()->set(AuthorizationCheckerInterface::class, new WorkflowTestAuthorization(true));
    }

    private function request(string $method, string $path, array $payload = [], int $status = 200, array $headers = []): array
    {
        $this->client->jsonRequest($method, $path, $payload, $headers);
        self::assertResponseStatusCodeSame($status);

        return json_decode($this->client->getResponse()->getContent(), true) ?? [];
    }

    public function testWorkspaceQueriesDoNotChangeBusinessStateOrProcessIntegrationMessages(): void
    {
        $data = $this->invitationFixture();
        // Leave an occurrence event pending and an invitation expired. Reads must not repair either.
        $o = $data['o'];
        $this->request('PUT', '/api/events/'.$o['eventId'].'/occurrences/'.$o['id'], ['startsAt' => '2030-01-06T10:00', 'endsAt' => '2030-01-06T12:00', 'location' => 'Changed room', 'status' => 'scheduled'], 204);
        self::getContainer()->get(\Symfony\Component\Clock\ClockInterface::class)->modify('+1 day');
        $snapshot = function (): array {
            $state = [];
            foreach ($this->db->fetchFirstColumn("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name") as $table) {
                $rows = array_map(static fn ($row) => json_encode($row, JSON_THROW_ON_ERROR), $this->db->fetchAllAssociative('SELECT * FROM '.$this->db->quoteIdentifier($table)));
                sort($rows);
                $state[$table] = $rows;
            }

            return $state;
        };
        $before = $snapshot();
        foreach (['/api/events', '/api/volunteering', '/api/volunteer-rota', '/api/children', '/api/attendance', '/api/ministries', '/api/groups', '/api/giving', '/api/communications', '/api/forms', '/api/service-plans', '/api/reports/segments', '/api/reports/rules'] as $path) {
            $this->request('GET', $path);
            self::assertSame($before, $snapshot(), $path.' changed persistent state');
        }
    }

    public function testFieldAuditRequiresContextManagementAndValidPagination(): void
    {
        $id = Uuid::v7()->toRfc4122();
        $this->request('GET', '/api/audit/giving/'.$id, [], 501);
        $this->request('GET', '/api/audit/giving/'.$id.'?before=0', [], 422);
        self::getContainer()->get(AuthorizationCheckerInterface::class)->permissions = ['giving.manage'];
        $this->request('GET', '/api/audit/identity/'.$id, [], 403);
        self::getContainer()->get(AuthorizationCheckerInterface::class)->permissions = [];
        $this->request('GET', '/api/audit/giving/'.$id, [], 403);
    }

    private function occurrence(): array
    {
        $event = $this->request('POST', '/api/events', ['title' => 'Sunday service', 'type' => 'service', 'startsAt' => '2030-01-06T09:00', 'endsAt' => '2030-01-06T12:00', 'location' => 'Church', 'timezone' => 'UTC'], 201);

        return $this->request('GET', '/api/events/'.$event['id'])['occurrences'][0];
    }

    private function volunteer(array $occurrence): array
    {
        $role = $this->request('POST', '/api/volunteering/roles', ['name' => 'Children helper', 'requiresScreening' => true], 201);
        $profile = $this->request('POST', '/api/volunteering/profiles', ['personId' => $this->ids['adult'], 'approvedRoleIds' => [$role['id']], 'availability' => [['startsAt' => '2030-01-06T09:00:00.000Z', 'endsAt' => '2030-01-06T12:00:00.000Z']], 'screeningValidUntil' => '2099-12-31'], 201);
        $shift = $this->request('POST', '/api/volunteering/shifts', ['occurrenceId' => $occurrence['id'], 'roleId' => $role['id'], 'startsAt' => '2030-01-06T09:00:00.000Z', 'endsAt' => '2030-01-06T12:00:00.000Z', 'positions' => 1], 201);
        $assignment = $this->request('POST', '/api/volunteering/assignments', ['shiftId' => $shift['id'], 'personId' => $this->ids['adult']], 201);

        return compact('role', 'profile', 'shift', 'assignment');
    }

    public function testLostWorkflowLockRollsBackTheDatabaseChange(): void
    {
        $lock = $this->createMock(\Symfony\Component\Lock\SharedLockInterface::class);
        $lock->expects(self::once())->method('acquire')->with(true)->willReturn(true);
        $lock->expects(self::once())->method('isAcquired')->willReturn(false);
        $lock->expects(self::never())->method('isExpired');
        $lock->expects(self::once())->method('release');
        $factory = $this->createMock(LockFactory::class);
        $factory->expects(self::once())->method('createLock')->willReturn($lock);
        $transactions = new \App\Shared\Infrastructure\Persistence\WorkflowTransactionManager($this->db, $factory, new \Symfony\Component\HttpFoundation\RequestStack());
        try {
            $transactions->run(fn () => $this->db->insert('user_accounts', ['id' => Uuid::v7()->toRfc4122(), 'username' => 'rolled-back']));
            self::fail('A lost lock must abort commit.');
        } catch (DomainException) {
        }
        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM user_accounts'));
    }

    public function testOptimisticPersistenceRejectsStaleWrites(): void
    {
        $repository = self::getContainer()->get(\App\Volunteering\Domain\Repository\VolunteeringRepository::class);
        $role = new \App\Volunteering\Domain\Model\VolunteerRole('Welcome');
        $repository->save($role);
        $first = $repository->find('roles', $role->id);
        $stale = $repository->find('roles', $role->id);
        $repository->save($first);
        $this->expectException(DomainException::class);
        $repository->save($stale);
    }

    public function testPermissionsAreResolvedFromTheActiveAccount(): void
    {
        $permissionRepository = $this->createStub(\App\IdentityAccess\Application\ReadModel\IdentityAccessReadRepository::class);
        $permissionRepository->method('permissionCodesForUser')->willReturn(['giving.manage', 'people.manage', 'events.read']);
        $voter = new \App\IdentityAccess\Infrastructure\Security\ContextPermissionVoter($permissionRepository);
        $account = \App\IdentityAccess\Domain\Model\UserAccount::create(\App\IdentityAccess\Domain\ValueObject\UserId::generate(), \App\IdentityAccess\Domain\ValueObject\Username::fromString('manager'), \App\IdentityAccess\Domain\ValueObject\PasswordHash::fromString('hash'), null);
        $token = new \Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken(new \App\IdentityAccess\Infrastructure\Security\SymfonyUser($account), 'main', ['ROLE_USER']);
        self::assertSame(\Symfony\Component\Security\Core\Authorization\Voter\VoterInterface::ACCESS_GRANTED, $voter->vote($token, null, ['giving.manage']));
        self::assertSame(\Symfony\Component\Security\Core\Authorization\Voter\VoterInterface::ACCESS_DENIED, $voter->vote($token, null, ['children.manage']));
        self::assertSame(\Symfony\Component\Security\Core\Authorization\Voter\VoterInterface::ACCESS_GRANTED, $voter->vote($token, null, ['people.read']));
        self::assertSame(\Symfony\Component\Security\Core\Authorization\Voter\VoterInterface::ACCESS_GRANTED, $voter->vote($token, null, ['people.manage']));
        self::assertSame(\Symfony\Component\Security\Core\Authorization\Voter\VoterInterface::ACCESS_GRANTED, $voter->vote($token, null, ['events.read']));
        self::assertSame(\Symfony\Component\Security\Core\Authorization\Voter\VoterInterface::ACCESS_DENIED, $voter->vote($token, null, ['events.manage']));
        $account->disable();
        self::assertSame(\Symfony\Component\Security\Core\Authorization\Voter\VoterInterface::ACCESS_DENIED, $voter->vote($token, null, ['giving.manage']));
    }

    public function testMalformedCollectionsReturnValidationErrors(): void
    {
        $this->request('POST', '/api/volunteering/profiles', ['personId' => $this->ids['adult'], 'approvedRoleIds' => [123], 'availability' => []], 422);
        $this->request('POST', '/api/volunteering/profiles', ['personId' => $this->ids['adult'], 'approvedRoleIds' => [], 'availability' => ['invalid']], 422);
        $this->request('POST', '/api/communications/campaigns', ['name' => 'Test', 'templateId' => $this->ids['adult'], 'recipientIds' => [123]], 422);
        $this->request('POST', '/api/giving/gifts', ['amountMinor' => 100, 'currency' => 'ZAR', 'allocations' => ['invalid'], 'receivedOn' => '2030-01-01', 'method' => 'cash', 'idempotencyKey' => 'malformed'], 422);
    }

    public function testVolunteeringEligibilityConflictsVacanciesAndDurableCancellation(): void
    {
        $occurrence = $this->occurrence();
        $data = $this->volunteer($occurrence);
        $this->request('POST', '/api/volunteering/assignments', ['shiftId' => $data['shift']['id'], 'personId' => $this->ids['adult']], 409);
        $roster = $this->request('GET', '/api/volunteering');
        self::assertSame(0, $roster['shifts'][0]['vacancies']);
        $otherShift = $this->request('POST', '/api/volunteering/shifts', ['occurrenceId' => $occurrence['id'], 'roleId' => $data['role']['id'], 'startsAt' => '2030-01-06T10:00:00Z', 'endsAt' => '2030-01-06T11:00:00Z', 'positions' => 1], 201);
        $this->request('POST', '/api/volunteering/assignments', ['shiftId' => $otherShift['id'], 'personId' => $this->ids['adult']], 409);
        $this->request('POST', '/api/volunteering/assignments/'.$data['assignment']['id'].'/status', ['status' => 'declined']);
        $expired = array_intersect_key($data['profile'], array_flip(['personId', 'approvedRoleIds', 'availability', 'qualifications', 'qualificationsValidUntil', 'screeningValidUntil', 'active']));
        $expired['screeningValidUntil'] = '2029-12-31';
        $this->request('PUT', '/api/volunteering/profiles/'.$data['profile']['id'], $expired);
        $this->request('POST', '/api/volunteering/assignments/'.$data['assignment']['id'].'/status', ['status' => 'assigned'], 409);
        $expired['screeningValidUntil'] = '2099-12-31';
        $this->request('PUT', '/api/volunteering/profiles/'.$data['profile']['id'], $expired);
        $this->request('POST', '/api/volunteering/assignments/'.$data['assignment']['id'].'/status', ['status' => 'assigned']);
        $this->request('PUT', '/api/events/'.$occurrence['eventId'].'/occurrences/'.$occurrence['id'], ['startsAt' => '2030-01-06T09:00', 'endsAt' => '2030-01-06T12:00', 'location' => 'Church', 'status' => 'cancelled'], 204);
        self::getContainer()->get(\App\Shared\Infrastructure\Outbox\OutboxProcessor::class)->drainAll();
        $roster = $this->request('GET', '/api/volunteering');
        self::assertSame('cancelled', $roster['assignments'][0]['status']);
        self::assertSame('cancelled', $roster['shifts'][0]['status']);
        $delivered = (int) $this->db->fetchOne('SELECT COUNT(*) FROM integration_deliveries');
        $this->request('GET', '/api/volunteering');
        self::assertSame($delivered, (int) $this->db->fetchOne('SELECT COUNT(*) FROM integration_deliveries'));
        $this->request('POST', '/api/volunteering/assignments/'.$data['assignment']['id'].'/status', ['status' => 'assigned'], 409);
        $this->request('PUT', '/api/events/'.$occurrence['eventId'].'/occurrences/'.$occurrence['id'], ['startsAt' => '2030-01-06T09:00', 'endsAt' => '2030-01-06T12:00', 'location' => 'Church', 'status' => 'scheduled'], 204);
        self::getContainer()->get(\App\Shared\Infrastructure\Outbox\OutboxProcessor::class)->drainAll();
        self::assertSame('cancelled', $this->request('GET', '/api/volunteering')['assignments'][0]['status']);
    }

    private function stationFixture(): array
    {
        $occurrence = $this->occurrence();
        $this->volunteer($occurrence);
        $programme = $this->request('POST', '/api/children/programmes', ['name' => 'Sunday school'], 201);
        $class = $this->request('POST', '/api/children/classes', ['programmeId' => $programme['id'], 'name' => 'Juniors', 'minAge' => 5, 'maxAge' => 12, 'capacity' => 20], 201);
        $this->request('POST', '/api/children/enrolments', ['classId' => $class['id'], 'childId' => $this->ids['child'], 'enrolledOn' => '2030-01-01'], 201);
        $authorisation = $this->request('POST', '/api/children/authorisations', ['childId' => $this->ids['child'], 'collectorId' => $this->ids['collector'], 'validUntil' => '2030-12-31'], 201);
        $session = $this->request('POST', '/api/children/sessions', ['classId' => $class['id'], 'occurrenceId' => $occurrence['id'], 'staffPersonIds' => [$this->ids['adult']]], 201);
        $this->useGroupLeader();
        self::getContainer()->get(AuthorizationCheckerInterface::class)->permissions = ['children.station'];

        return compact('session', 'occurrence', 'authorisation');
    }

    public function testStationLabelsRequireIdentityCurrentAuthorityAndCannotBypassThroughStaffApi(): void
    {
        $fixture = $this->stationFixture();
        $id = $fixture['session']['id'];
        self::assertCount(1, $this->request('GET', '/api/children-station')['items']);
        self::assertStringContainsString('no-store', $this->client->getResponse()->headers->get('Cache-Control'));
        $roster = $this->request('GET', '/api/children-station/sessions/'.$id);
        self::assertSame([$this->ids['child']], array_column($roster['items'], 'id'));
        self::assertArrayNotHasKey('dateOfBirth', $roster['items'][0]);
        $this->request('GET', '/api/children', [], 403);
        $this->request('GET', '/api/children-station/sessions/'.$id.'/children/'.$this->ids['child'].'/support', [], 403);
        $this->request('POST', '/api/children-station/sessions/'.$id.'/arrivals', ['childId' => $this->ids['otherChild']], 409);
        $label = $this->request('POST', '/api/children-station/sessions/'.$id.'/arrivals', ['childId' => $this->ids['child']], 201);
        self::assertMatchesRegularExpression('/^[A-F0-9]{12}$/', $label['code']);
        self::assertArrayNotHasKey('note', $label);
        $checkin = $this->request('GET', '/api/children-station/sessions/'.$id)['items'][0]['checkIn'];
        $path = '/api/children-station/checkins/'.$checkin['id'];
        $payload = ['collectorId' => $this->ids['collector'], 'pickupCode' => $label['code'], 'expectedVersion' => $checkin['version'], 'identityVerified' => true];
        $this->request('POST', $path.'/collect', array_replace($payload, ['identityVerified' => false]), 409);
        $this->request('POST', $path.'/collect', array_replace($payload, ['pickupCode' => 'AAAAAAAAAAAA']), 409);
        $this->request('POST', $path.'/collect', array_replace($payload, ['expectedVersion' => 99]), 409);
        $reprint = $this->request('POST', $path.'/label', ['expectedVersion' => $label['labelVersion']]);
        self::assertSame($label['code'], $reprint['code']);
        $this->request('POST', $path.'/label', ['expectedVersion' => $label['labelVersion']], 409);
        $replacement = $this->request('POST', $path.'/label', ['expectedVersion' => $reprint['labelVersion'], 'replacementReason' => 'Pickup copy lost']);
        self::assertNotSame($label['code'], $replacement['code']);
        $this->request('POST', $path.'/collect', $payload, 409);
        self::getContainer()->get(AuthorizationCheckerInterface::class)->permissions = ['children.manage', 'children.station'];
        $this->request('POST', '/api/children/checkins/'.$checkin['id'].'/checkout', ['collectorId' => $this->ids['collector'], 'staffPersonId' => $this->ids['adult']], 409);
        $this->request('POST', '/api/children/authorisations/'.$fixture['authorisation']['id'].'/revoke');
        $payload['pickupCode'] = $replacement['code'];
        $this->request('POST', $path.'/collect', $payload, 409);
        $this->request('POST', '/api/children/authorisations', ['childId' => $this->ids['child'], 'collectorId' => $this->ids['collector'], 'validUntil' => '2030-12-31'], 201);
        $this->request('POST', '/api/children/sessions/'.$id.'/cancel');
        $collected = $this->request('POST', $path.'/collect', $payload);
        self::assertTrue($collected['identityVerified']);
        self::assertSame($replacement['labelVersion'], $collected['pickupLabelVersion']);
        $this->request('POST', $path.'/collect', $payload, 409);
        $this->request('POST', $path.'/label', ['expectedVersion' => $replacement['labelVersion']], 409);
    }

    public function testStationScopesNotesAndRevokesAccessImmediately(): void
    {
        $fixture = $this->stationFixture();
        $id = $fixture['session']['id'];
        $notePath = '/api/children-station/sessions/'.$id.'/children/'.$this->ids['child'].'/support';
        self::getContainer()->get(AuthorizationCheckerInterface::class)->permissions = ['children.station', 'children.support'];
        $this->request('PUT', $notePath, ['note' => 'Keep inhaler with supervisor', 'expectedVersion' => 0], 403);
        self::getContainer()->get(AuthorizationCheckerInterface::class)->permissions[] = 'children.manage';
        $note = $this->request('PUT', $notePath, ['note' => 'Keep inhaler with supervisor', 'expectedVersion' => 0]);
        self::assertSame('Keep inhaler with supervisor', $note['note']);
        $this->request('PUT', $notePath, ['note' => 'Stale update', 'expectedVersion' => 0], 409);
        self::assertStringNotContainsString('inhaler', json_encode($this->request('GET', '/api/children')));
        self::assertStringNotContainsString('inhaler', json_encode($this->request('GET', '/api/children-station/sessions/'.$id)));
        $this->request('GET', '/api/children-station/sessions/'.$id.'/children/'.$this->ids['otherChild'].'/support', [], 403);
        $repository = self::getContainer()->get(\App\ChildrenMinistry\Domain\Repository\ChildrenMinistryRepository::class);
        $unassigned = new \App\ChildrenMinistry\Domain\Model\MinistrySession($fixture['session']['classId'], $fixture['occurrence']['id'], [$this->ids['collector']]);
        $repository->save($unassigned);
        $this->request('GET', '/api/children-station/sessions/'.$unassigned->id, [], 403);
        $this->request('POST', '/api/children-station/sessions/'.$unassigned->id.'/arrivals', ['childId' => $this->ids['child']], 403);
        self::getContainer()->get(AuthorizationCheckerInterface::class)->permissions = [];
        $this->request('GET', '/api/children-station', [], 403);
        $this->request('GET', $notePath, [], 403);
    }

    private function reportingStaff(): string
    {
        $this->useGroupLeader();
        $user = self::getContainer()->get(\Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface::class)->getToken()->getUser()->account();
        $id = $user->id()->toString();
        $this->db->insert('user_accounts', ['id' => $id, 'username' => 'followup-manager', 'status' => 'active']);
        $role = Uuid::v7()->toRfc4122();
        $this->db->insert('user_roles', ['user_id' => $id, 'role_id' => $role]);
        foreach (['reports.manage', 'followups.manage', 'people.read', 'attendance.manage', 'ministries.manage'] as $code) {
            $pid = Uuid::v7()->toRfc4122();
            $this->db->insert('permissions', ['id' => $pid, 'code' => $code]);
            $this->db->insert('role_permissions', ['role_id' => $role, 'permission_id' => $pid]);
        }
        self::getContainer()->get(AuthorizationCheckerInterface::class)->permissions = null;

        return $id;
    }

    public function testReportingSegmentsAreLiveSourceScopedAndCsvIsSafe(): void
    {
        $o = $this->occurrence();
        $this->request('POST', '/api/attendance/entries', ['occurrenceId' => $o['id'], 'personId' => $this->ids['child'], 'status' => 'present'], 201);
        $this->request('POST', '/api/attendance/entries', ['occurrenceId' => $o['id'], 'personId' => $this->ids['adult'], 'status' => 'absent'], 201);
        $filters = ['status' => 'active', 'ageGroup' => 'adult', 'attendanceStatus' => 'no_recorded_presence', 'lookbackDays' => 7];
        $segment = $this->request('POST', '/api/reports/segments', ['name' => 'Adults without recorded presence', 'filters' => $filters], 201);
        $matched = $this->request('GET', '/api/reports/segments/'.$segment['id'].'/members');
        self::assertSame([$this->ids['adult']], array_column($matched['items'], 'id'));
        self::assertArrayNotHasKey('email', $matched['items'][0]);
        $this->request('PUT', '/api/reports/segments/'.$segment['id'], ['name' => 'Changed', 'filters' => $filters, 'expectedVersion' => 99], 409);
        $prospects = $this->request('POST', '/api/reports/segments', ['name' => 'Prospects', 'filters' => ['status' => 'prospect']], 201);
        $this->client->request('GET', '/api/reports/segments/'.$prospects['id'].'/members.csv');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString("'=FORMULA", $this->client->getResponse()->getContent());
        $this->request('POST', '/api/reports/segments/preview', ['filters' => ['rawSql' => 'SELECT *']], 422);
        self::getContainer()->get(AuthorizationCheckerInterface::class)->permissions = ['reports.read', 'people.read'];
        $this->request('GET', '/api/reports/segments/'.$segment['id'].'/members', [], 403);
        self::assertCount(1, $this->request('GET', '/api/reports/segments')['items']);
        $summary = $this->request('GET', '/api/reports/summary?from=2030-01-01&to=2030-01-06');
        self::assertSame(['people'], array_keys($summary['sections']));
        $this->request('POST', '/api/reports/segments', ['name' => 'Escalation', 'filters' => []], 403);
    }

    public function testOperationalReportDeduplicatesChildrenAndSeparatesVisitors(): void
    {
        $o = $this->occurrence();
        $repo = self::getContainer()->get(\App\ChildrenMinistry\Domain\Repository\ChildrenMinistryRepository::class);
        $session = new \App\ChildrenMinistry\Domain\Model\MinistrySession(Uuid::v7()->toRfc4122(), $o['id'], [$this->ids['adult']]);
        $repo->save($session);
        $repo->save(new \App\ChildrenMinistry\Domain\Model\ChildCheckIn($session->id, $this->ids['child'], '2030-01-06T09:00:00Z', $this->ids['adult']));
        $this->request('POST', '/api/attendance/entries', ['occurrenceId' => $o['id'], 'personId' => $this->ids['child'], 'status' => 'present'], 201);
        $this->request('POST', '/api/attendance/visitors', ['occurrenceId' => $o['id'], 'count' => 5], 201);
        $r = $this->request('GET', '/api/reports/summary?from=2030-01-01&to=2030-01-06');
        self::assertSame(1, $r['sections']['attendance']['days'][0]['present']);
        self::assertSame(5, $r['sections']['attendance']['days'][0]['visitors']);
        self::assertSame(1, $r['sections']['attendance']['uniquePeoplePresent']);
        $this->request('GET', '/api/reports/summary?from=2028-01-01&to=2030-01-06', [], 422);
        $this->request('POST', '/api/reports/segments/preview', ['filters' => ['attendanceStatus' => 'absent', 'from' => '2030-01-01', 'to' => '2030-02-01']], 422);
        self::assertCount(0, $this->request('POST', '/api/reports/segments/preview', ['filters' => ['attendanceStatus' => 'absent', 'lookbackDays' => 7]])['items']);
    }

    public function testFollowupRulesAreIdempotentAndPauseForChangedSegmentsAndRevokedAccess(): void
    {
        $owner = $this->reportingStaff();
        $segment = $this->request('POST', '/api/reports/segments', ['name' => 'Adults', 'filters' => ['status' => 'active', 'ageGroup' => 'adult']], 201);
        $payload = ['name' => 'Welcome contact', 'segmentId' => $segment['id'], 'segmentVersion' => $segment['version'], 'assigneeId' => $owner, 'taskTitle' => 'Arrange a welcome conversation', 'dueInDays' => 3, 'active' => false];
        $rule = $this->request('POST', '/api/reports/rules', $payload, 201);
        self::assertSame(0, $this->request('POST', '/api/reports/rules/'.$rule['id'].'/run')['created']);
        $payload['active'] = true;
        $payload['expectedVersion'] = $rule['version'];
        $rule = $this->request('PUT', '/api/reports/rules/'.$rule['id'], $payload);
        self::assertSame(1, $this->request('POST', '/api/reports/rules/'.$rule['id'].'/run')['created']);
        self::assertSame(0, $this->request('POST', '/api/reports/rules/'.$rule['id'].'/run')['created']);
        $task = $this->request('GET', '/api/followups')['items'][0];
        self::assertSame('2030-01-09', $task['dueOn']);
        $this->request('POST', '/api/followups/'.$task['id'].'/status', ['expectedVersion' => $task['version'], 'status' => 'completed', 'note' => ''], 422);
        $completed = $this->request('POST', '/api/followups/'.$task['id'].'/status', ['expectedVersion' => $task['version'], 'status' => 'completed', 'note' => 'Welcome conversation arranged.']);
        self::assertSame('completed', $completed['status']);
        $this->request('POST', '/api/followups/'.$task['id'].'/status', ['expectedVersion' => $task['version'], 'status' => 'in_progress'], 409);
        self::assertSame(0, self::getContainer()->get(\App\Reporting\Application\Command\SweepFollowupRules\SweepFollowupRulesHandler::class)(new \App\Reporting\Application\Command\SweepFollowupRules\SweepFollowupRulesCommand())['created']);
        $this->request('PUT', '/api/reports/segments/'.$segment['id'], ['name' => 'Prospects', 'filters' => ['status' => 'prospect'], 'expectedVersion' => $segment['version']]);
        self::assertTrue($this->request('POST', '/api/reports/rules/'.$rule['id'].'/run')['paused']);
        self::assertFalse($this->request('GET', '/api/reports/rules')['items'][0]['active']);
        self::assertCount(0, $this->request('GET', '/api/communications')['campaigns']);
    }

    public function testAutomationPausesWhenOnlyAssigneeSourcePermissionIsRevoked(): void
    {
        $owner = $this->reportingStaff();
        $assignee = Uuid::v7()->toRfc4122();
        $role = Uuid::v7()->toRfc4122();
        $this->db->insert('user_accounts', ['id' => $assignee, 'username' => 'followup-operator', 'status' => 'active']);
        $this->db->insert('user_roles', ['user_id' => $assignee, 'role_id' => $role]);
        $work = Uuid::v7()->toRfc4122();
        $this->db->insert('permissions', ['id' => $work, 'code' => 'followups.work']);
        $attendance = $this->db->fetchOne("SELECT id FROM permissions WHERE code='attendance.manage'");
        foreach ([$work, $attendance, $this->db->fetchOne("SELECT id FROM permissions WHERE code='people.read'")] as $id) {
            $this->db->insert('role_permissions', ['role_id' => $role, 'permission_id' => $id]);
        }
        $segment = $this->request('POST', '/api/reports/segments', ['name' => 'No recorded attendance', 'filters' => ['status' => 'active', 'ageGroup' => 'adult', 'attendanceStatus' => 'no_recorded_presence', 'lookbackDays' => 7]], 201);
        $rule = $this->request('POST', '/api/reports/rules', ['name' => 'Review access', 'segmentId' => $segment['id'], 'segmentVersion' => 1, 'assigneeId' => $assignee, 'taskTitle' => 'Review records before contact', 'active' => true], 201);
        $this->db->delete('role_permissions', ['role_id' => $role, 'permission_id' => $attendance]);
        $result = self::getContainer()->get(\App\Reporting\Application\Command\SweepFollowupRules\SweepFollowupRulesHandler::class)(new \App\Reporting\Application\Command\SweepFollowupRules\SweepFollowupRulesCommand());
        self::assertSame(1, $result['paused']);
        self::assertSame(0, $result['created']);
        self::assertStringContainsString('Assignee lost', $this->request('GET', '/api/reports/rules')['items'][0]['reviewReason']);
        self::assertCount(0, $this->request('GET', '/api/followups')['items']);
        $this->request('POST', '/api/followups', ['personId' => $this->ids['adult'], 'assigneeId' => $assignee, 'title' => 'Invalid source assignment', 'dueOn' => '2030-01-07', 'segmentId' => $segment['id']], 409);
    }

    public function testFollowupAssigneesCannotReadOthersTasksAndPermissionLossPausesAutomation(): void
    {
        $owner = $this->reportingStaff();
        $task = $this->request('POST', '/api/followups', ['personId' => $this->ids['child'], 'assigneeId' => $owner, 'title' => 'Check contact request', 'dueOn' => '2030-01-06'], 201);
        $this->request('POST', '/api/followups', ['personId' => $this->ids['child'], 'assigneeId' => Uuid::v7()->toRfc4122(), 'title' => 'Bad assignment', 'dueOn' => '2030-01-06'], 409);
        self::getContainer()->get(AuthorizationCheckerInterface::class)->permissions = ['followups.work', 'people.read'];
        self::assertCount(1, $this->request('GET', '/api/followups')['items']);
        $token = self::getContainer()->get(\Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface::class)->getToken();
        $other = \App\IdentityAccess\Domain\Model\UserAccount::create(\App\IdentityAccess\Domain\ValueObject\UserId::generate(), \App\IdentityAccess\Domain\ValueObject\Username::fromString('other-staff'), \App\IdentityAccess\Domain\ValueObject\PasswordHash::fromString('hash'), $this->ids['collector']);
        $original = $token->getUser();
        $token->setUser(new \App\IdentityAccess\Infrastructure\Security\SymfonyUser($other));
        self::assertCount(0, $this->request('GET', '/api/followups')['items']);
        $this->request('GET', '/api/followups/'.$task['id'], [], 403);
        $token->setUser($original);
        self::getContainer()->get(AuthorizationCheckerInterface::class)->permissions = null;
        $segment = $this->request('POST', '/api/reports/segments', ['name' => 'All active', 'filters' => ['status' => 'active']], 201);
        $rule = $this->request('POST', '/api/reports/rules', ['name' => 'Disabled owner', 'segmentId' => $segment['id'], 'segmentVersion' => 1, 'assigneeId' => $owner, 'taskTitle' => 'Contact', 'active' => true], 201);
        $this->db->update('user_accounts', ['status' => 'disabled'], ['id' => $owner]);
        $result = self::getContainer()->get(\App\Reporting\Application\Command\SweepFollowupRules\SweepFollowupRulesHandler::class)(new \App\Reporting\Application\Command\SweepFollowupRules\SweepFollowupRulesCommand());
        self::assertSame(1, $result['paused']);
        self::assertSame(0, $result['created']);
        self::assertCount(1, $this->request('GET', '/api/followups')['items']);
    }

    public function testChildrenStaffCanServePartOfAMultiDayOccurrence(): void
    {
        $event = $this->request('POST', '/api/events', ['title' => 'Multi-day event', 'type' => 'conference', 'startsAt' => '2030-01-06T08:00', 'endsAt' => '2030-01-08T12:00', 'location' => 'Church', 'timezone' => 'UTC'], 201);
        $occurrence = $this->request('GET', '/api/events/'.$event['id'])['occurrences'][0];
        $volunteer = $this->volunteer($occurrence);
        $programme = $this->request('POST', '/api/children/programmes', ['name' => 'Children'], 201);
        $class = $this->request('POST', '/api/children/classes', ['programmeId' => $programme['id'], 'name' => 'Juniors', 'minAge' => 5, 'maxAge' => 12, 'capacity' => 20], 201);
        $payload = ['classId' => $class['id'], 'occurrenceId' => $occurrence['id'], 'staffPersonIds' => [$this->ids['adult']]];
        $session = $this->request('POST', '/api/children/sessions', $payload, 201);
        self::assertSame('scheduled', $session['status']);

        // Screening only needs to last through the shift, not the multi-day event.
        $profile = array_intersect_key($volunteer['profile'], array_flip(['personId', 'approvedRoleIds', 'availability', 'qualifications', 'qualificationsValidUntil', 'screeningValidUntil', 'active']));
        $profile['screeningValidUntil'] = '2030-01-06';
        $this->request('PUT', '/api/volunteering/profiles/'.$volunteer['profile']['id'], $profile);
        $this->request('PUT', '/api/children/sessions/'.$session['id'], $payload);
        $profile['screeningValidUntil'] = '2030-01-05';
        $this->request('PUT', '/api/volunteering/profiles/'.$volunteer['profile']['id'], $profile);
        $this->request('PUT', '/api/children/sessions/'.$session['id'], $payload, 409);
        $profile['screeningValidUntil'] = '2030-01-06';
        $this->request('PUT', '/api/volunteering/profiles/'.$volunteer['profile']['id'], $profile);

        $otherOccurrence = $this->occurrence();
        $otherPayload = $payload;
        $otherPayload['occurrenceId'] = $otherOccurrence['id'];
        $this->request('POST', '/api/children/sessions', $otherPayload, 409);
        $this->request('POST', '/api/volunteering/assignments/'.$volunteer['assignment']['id'].'/status', ['status' => 'cancelled']);
        $this->request('PUT', '/api/children/sessions/'.$session['id'], $payload, 409);
    }

    public function testChildrenEnrolmentAuthorisedPickupAndCancellationPreserveCustody(): void
    {
        $occurrence = $this->occurrence();
        $volunteer = $this->volunteer($occurrence);
        $programme = $this->request('POST', '/api/children/programmes', ['name' => 'Sunday school'], 201);
        $class = $this->request('POST', '/api/children/classes', ['programmeId' => $programme['id'], 'name' => 'Juniors', 'minAge' => 5, 'maxAge' => 12, 'capacity' => 20], 201);
        $this->request('POST', '/api/children/enrolments', ['classId' => $class['id'], 'childId' => $this->ids['child'], 'enrolledOn' => '2030-01-01'], 201);
        $this->request('POST', '/api/children/enrolments', ['classId' => $class['id'], 'childId' => $this->ids['child'], 'enrolledOn' => '2030-01-01'], 409);
        $this->request('POST', '/api/children/enrolments', ['classId' => $class['id'], 'childId' => $this->ids['adult'], 'enrolledOn' => '2030-01-01'], 409);
        $session = $this->request('POST', '/api/children/sessions', ['classId' => $class['id'], 'occurrenceId' => $occurrence['id'], 'staffPersonIds' => [$this->ids['adult']]], 201);
        $this->request('POST', '/api/children/sessions', ['classId' => $class['id'], 'occurrenceId' => $occurrence['id'], 'staffPersonIds' => [$this->ids['collector']]], 409);
        $sessionPayload = ['classId' => $class['id'], 'occurrenceId' => $occurrence['id'], 'staffPersonIds' => [$this->ids['adult']]];
        $this->request('PUT', '/api/events/'.$occurrence['eventId'].'/occurrences/'.$occurrence['id'], ['startsAt' => '2030-01-06T09:00', 'endsAt' => '2030-01-06T12:00', 'location' => 'New classroom', 'status' => 'scheduled'], 204);
        self::getContainer()->get(\App\Shared\Infrastructure\Outbox\OutboxProcessor::class)->drainAll();
        self::assertSame('needs_review', $this->request('GET', '/api/children')['sessions'][0]['status']);
        $this->request('PUT', '/api/children/sessions/'.$session['id'], $sessionPayload, 409);
        $shiftPayload = array_intersect_key($volunteer['shift'], array_flip(['occurrenceId', 'roleId', 'startsAt', 'endsAt', 'positions', 'status']));
        $shiftPayload['status'] = 'open';
        $this->request('PUT', '/api/volunteering/shifts/'.$volunteer['shift']['id'], $shiftPayload);
        $this->request('POST', '/api/volunteering/assignments/'.$volunteer['assignment']['id'].'/status', ['status' => 'assigned']);
        self::assertSame('scheduled', $this->request('PUT', '/api/children/sessions/'.$session['id'], $sessionPayload)['status']);
        $checkin = $this->request('POST', '/api/children/checkins', ['sessionId' => $session['id'], 'childId' => $this->ids['child'], 'staffPersonId' => $this->ids['adult']], 201);
        $this->request('POST', '/api/children/checkins', ['sessionId' => $session['id'], 'childId' => $this->ids['child'], 'staffPersonId' => $this->ids['adult']], 409);
        $this->request('POST', '/api/attendance/entries', ['occurrenceId' => $occurrence['id'], 'personId' => $this->ids['child'], 'status' => 'present'], 201);
        $combined = $this->request('GET', '/api/attendance');
        self::assertSame(1, $combined['totals'][0]['present']);
        $this->request('POST', '/api/attendance/entries', ['occurrenceId' => $occurrence['id'], 'personId' => $this->ids['child'], 'status' => 'absent'], 409);
        $checkout = ['collectorId' => $this->ids['collector'], 'staffPersonId' => $this->ids['adult']];
        $this->request('POST', '/api/children/checkins/'.$checkin['id'].'/checkout', $checkout, 409);
        $authorisation = $this->request('POST', '/api/children/authorisations', ['childId' => $this->ids['child'], 'collectorId' => $this->ids['collector'], 'validUntil' => '2030-12-31'], 201);
        $this->request('POST', '/api/children/authorisations/'.$authorisation['id'].'/revoke');
        $this->request('POST', '/api/children/checkins/'.$checkin['id'].'/checkout', $checkout, 409);
        $this->request('POST', '/api/children/authorisations', ['childId' => $this->ids['child'], 'collectorId' => $this->ids['collector'], 'validUntil' => '2030-12-31'], 201);
        $this->request('PUT', '/api/events/'.$occurrence['eventId'].'/occurrences/'.$occurrence['id'], ['startsAt' => '2030-01-06T09:00', 'endsAt' => '2030-01-06T12:00', 'location' => 'Church', 'status' => 'cancelled'], 204);
        self::getContainer()->get(\App\Shared\Infrastructure\Outbox\OutboxProcessor::class)->drainAll();
        $state = $this->request('GET', '/api/children');
        self::assertSame('cancelled', $state['sessions'][0]['status']);
        self::assertNull($state['checkins'][0]['checkedOutAt']);
        $this->request('POST', '/api/children/checkins', ['sessionId' => $session['id'], 'childId' => $this->ids['otherChild'], 'staffPersonId' => $this->ids['adult']], 409);
        $result = $this->request('POST', '/api/children/checkins/'.$checkin['id'].'/checkout', $checkout);
        self::assertSame($this->ids['collector'], $result['collectorId']);
        self::assertSame($this->ids['adult'], $result['checkedOutBy']);
        self::assertNotNull($result['checkedOutAt']);
        $attendance = $this->request('GET', '/api/attendance');
        self::assertSame(1, $attendance['totals'][0]['present']);
        self::assertCount(1, $attendance['children']);
        $this->request('POST', '/api/children/checkins/'.$checkin['id'].'/checkout', $checkout, 409);
    }

    public function testGivingAllocationsIdempotencyReceiptsReversalAndCurrencyTotals(): void
    {
        $fund = $this->request('POST', '/api/giving/funds', ['name' => 'General'], 201);
        $payload = ['amountMinor' => 10000, 'currency' => 'ZAR', 'allocations' => [['fundId' => $fund['id'], 'amountMinor' => 10000]], 'receivedOn' => '2030-01-01', 'method' => 'bank', 'idempotencyKey' => 'deposit-1', 'donorId' => $this->ids['adult']];
        $gift = $this->request('POST', '/api/giving/gifts', $payload, 201);
        $repeated = $this->request('POST', '/api/giving/gifts', $payload, 201);
        self::assertSame($gift['id'], $repeated['id']);
        $bad = $payload;
        $bad['amountMinor'] = 12000;
        $bad['allocations'][0]['amountMinor'] = 12000;
        $this->request('POST', '/api/giving/gifts', $bad, 409);
        $bad['idempotencyKey'] = 'bad-allocation';
        $bad['allocations'][0]['amountMinor'] = 1;
        $this->request('POST', '/api/giving/gifts', $bad, 422);
        $receipt = $this->request('POST', '/api/giving/receipts', ['giftId' => $gift['id']], 201);
        self::assertSame($receipt['id'], $this->request('POST', '/api/giving/receipts', ['giftId' => $gift['id']], 201)['id']);
        $details = $this->request('GET', '/api/giving/receipts/'.$receipt['id']);
        self::assertSame(10000, $details['receipt']['amountMinor']);
        self::assertStringContainsString('no claim', $details['notice']);
        $other = $payload;
        $other['idempotencyKey'] = 'deposit-2';
        $other['currency'] = 'USD';
        $other['donorId'] = null;
        $this->request('POST', '/api/giving/gifts', $other, 201);
        $state = $this->request('GET', '/api/giving');
        self::assertCount(2, $state['totals']);
        $this->request('POST', '/api/giving/batches', ['reference' => 'BANK-1', 'giftIds' => [$gift['id']], 'expectedAmountMinor' => 9000, 'currency' => 'ZAR', 'reconciledOn' => '2030-01-02'], 409);
        $this->request('POST', '/api/giving/batches', ['reference' => 'BANK-1', 'giftIds' => [$gift['id']], 'expectedAmountMinor' => 10000, 'currency' => 'ZAR', 'reconciledOn' => '2030-01-02'], 201);
        $this->request('POST', '/api/giving/batches', ['reference' => 'BANK-2', 'giftIds' => [$gift['id']], 'expectedAmountMinor' => 10000, 'currency' => 'ZAR', 'reconciledOn' => '2030-01-02'], 409);
        $this->request('POST', '/api/giving/gifts/'.$gift['id'].'/reverse', ['reversedOn' => '2030-01-02', 'reason' => 'Recorded twice in source ledger'], 201);
        $this->request('POST', '/api/giving/gifts/'.$gift['id'].'/reverse', ['reversedOn' => '2030-01-02', 'reason' => 'Again'], 409);
        $state = $this->request('GET', '/api/giving');
        self::assertSame('reversed', $state['gifts'][0]['status']);
        self::assertTrue($state['receipts'][0]['void']);
        self::assertSame(0, $state['totals'][0]['netAmountMinor']);
        $period = $this->request('GET', '/api/giving?from=2030-01-02&to=2030-01-02');
        self::assertSame(-10000, $period['totals'][0]['netAmountMinor']);
        $this->request('PUT', '/api/giving/gifts/'.$gift['id'], ['amountMinor' => 1], 404);
    }

    public function testAttendanceDuplicatesCorrectionsAndVisitorTotals(): void
    {
        $occurrence = $this->occurrence();
        $payload = ['occurrenceId' => $occurrence['id'], 'personId' => $this->ids['adult'], 'status' => 'present'];
        $entry = $this->request('POST', '/api/attendance/entries', $payload, 201);
        $this->request('POST', '/api/attendance/entries', $payload, 409);
        $count = $this->request('POST', '/api/attendance/visitors', ['occurrenceId' => $occurrence['id'], 'count' => 5], 201);
        $state = $this->request('GET', '/api/attendance');
        self::assertSame(6, $state['totals'][0]['totalPresent']);
        $updated = $this->request('PUT', '/api/attendance/entries/'.$entry['id'], ['status' => 'excused', 'reason' => 'Clerk selected the wrong person']);
        self::assertSame('present', $updated['corrections'][0]['previousStatus']);
        $this->request('PUT', '/api/attendance/visitors/'.$count['id'], ['count' => 3, 'reason' => 'Recount']);
        $state = $this->request('GET', '/api/attendance');
        self::assertSame(3, $state['totals'][0]['totalPresent']);
        self::assertSame(1, $state['totals'][0]['excused']);
        $this->request('PUT', '/api/attendance/entries/'.$entry['id'], ['status' => 'absent', 'reason' => ''], 422);
        $this->request('POST', '/api/attendance/visitors', ['occurrenceId' => $occurrence['id'], 'count' => -1], 422);
    }

    public function testMinistriesMembershipLeadershipAndArchive(): void
    {
        $ministry = $this->request('POST', '/api/ministries/ministries', ['name' => 'Music team'], 201);
        $payload = ['ministryId' => $ministry['id'], 'personId' => $this->ids['adult'], 'joinedOn' => '2030-01-01'];
        $member = $this->request('POST', '/api/ministries/memberships', $payload, 201);
        $this->request('POST', '/api/ministries/memberships', $payload, 409);
        $leader = $this->request('POST', '/api/ministries/memberships/'.$member['id'].'/role', ['role' => 'leader']);
        self::assertSame('leader', $leader['role']);
        self::assertSame('member', $leader['history'][0]['previousRole']);
        $left = $this->request('POST', '/api/ministries/memberships/'.$member['id'].'/leave', ['leftOn' => '2030-01-06']);
        self::assertFalse($left['active']);
        $this->request('POST', '/api/ministries/ministries/'.$ministry['id'].'/archive');
        $this->request('POST', '/api/ministries/memberships', $payload, 409);
    }

    public function testCommunicationsConsentQueueIdempotencyAndRecheckingBeforeDelivery(): void
    {
        $template = $this->request('POST', '/api/communications/templates', ['name' => 'Welcome', 'channel' => 'email', 'subject' => 'Hello {{name}}', 'body' => 'Welcome {{name}}'], 201);
        $preference = $this->request('POST', '/api/communications/preferences', ['personId' => $this->ids['adult'], 'channel' => 'email', 'consent' => true, 'consentNote' => 'Signed contact consent'], 201);
        $campaign = $this->request('POST', '/api/communications/campaigns', ['name' => 'Welcome team', 'templateId' => $template['id'], 'recipientIds' => [$this->ids['adult'], $this->ids['collector']]], 201);
        $this->request('POST', '/api/communications/campaigns/'.$campaign['id'].'/queue');
        $this->request('POST', '/api/communications/campaigns/'.$campaign['id'].'/queue');
        $state = $this->request('GET', '/api/communications');
        self::assertCount(2, $state['deliveries']);
        self::assertSame('pending', $state['deliveries'][0]['status']);
        self::assertSame('suppressed', $state['deliveries'][1]['status']);
        self::assertSame('Welcome adult', $state['deliveries'][0]['body']);
        $this->request('POST', '/api/communications/preferences/'.$preference['id'].'/withdraw');
        self::assertFalse(self::getContainer()->get(\App\Communications\Application\Command\DeliverNextMessage\DeliverNextMessageHandler::class)(new \App\Communications\Application\Command\DeliverNextMessage\DeliverNextMessageCommand()));
        $state = $this->request('GET', '/api/communications');
        self::assertSame('suppressed', $state['deliveries'][0]['status']);
        $this->request('POST', '/api/communications/preferences', ['personId' => $this->ids['adult'], 'channel' => 'email', 'consent' => true, 'consentNote' => 'Reconfirmed'], 201);
        $campaign = $this->request('POST', '/api/communications/campaigns', ['name' => 'Second welcome', 'templateId' => $template['id'], 'recipientIds' => [$this->ids['adult']]], 201);
        $this->request('POST', '/api/communications/campaigns/'.$campaign['id'].'/queue');
        self::assertTrue(self::getContainer()->get(\App\Communications\Application\Command\DeliverNextMessage\DeliverNextMessageHandler::class)(new \App\Communications\Application\Command\DeliverNextMessage\DeliverNextMessageCommand()));
        $state = $this->request('GET', '/api/communications');
        self::assertSame('delivered', $state['deliveries'][2]['status']);
        $gateway = self::getContainer()->get(\App\Communications\Application\Port\MessageGateway::class);
        self::assertCount(1, $gateway->sent);
        self::assertFalse(self::getContainer()->get(\App\Communications\Application\Command\DeliverNextMessage\DeliverNextMessageHandler::class)(new \App\Communications\Application\Command\DeliverNextMessage\DeliverNextMessageCommand()));
    }

    public function testAuditAttributesChangesToTheAuthenticatedAccount(): void
    {
        $account = \App\IdentityAccess\Domain\Model\UserAccount::create(\App\IdentityAccess\Domain\ValueObject\UserId::generate(), \App\IdentityAccess\Domain\ValueObject\Username::fromString('manager'), \App\IdentityAccess\Domain\ValueObject\PasswordHash::fromString('hash'), null);
        $this->db->insert('user_accounts', ['id' => $account->id()->toString(), 'username' => 'manager']);
        $token = new \Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken(new \App\IdentityAccess\Infrastructure\Security\SymfonyUser($account), 'main', ['ROLE_USER']);
        self::getContainer()->get(\Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface::class)->setToken($token);
        $fund = $this->request('POST', '/api/giving/funds', ['name' => 'General'], 201);
        $audit = $this->request('GET', '/api/workflows/giving/'.$fund['id'].'/audit');
        self::assertSame($account->id()->toString(), $audit['entries'][0]['actorId']);
        self::assertSame('manager', $audit['entries'][0]['actorName']);
    }

    public function testOutboxFailurePreservesEarlierMessagesAndStopsInOrder(): void
    {
        $occurrence = $this->occurrence();
        $first = $this->db->fetchOne('SELECT id FROM integration_outbox ORDER BY sequence_no LIMIT 1');
        $failed = Uuid::v7()->toRfc4122();
        $later = Uuid::v7()->toRfc4122();
        foreach ([$failed => 2, $later => 1] as $id => $schema) {
            $this->db->insert('integration_outbox', ['id' => $id, 'type' => 'EventOccurrenceScheduled', 'schema_version' => $schema, 'payload' => json_encode(['occurrenceId' => $occurrence['id']]), 'created_at' => '2030-01-01']);
        }
        $processor = self::getContainer()->get(\App\Shared\Infrastructure\Outbox\OutboxProcessor::class);
        try {
            $processor->drain();
            self::fail('Unsupported schema must stop ordered delivery.');
        } catch (DomainException $error) {
            self::assertStringContainsString($failed, $error->getMessage());
        }
        self::assertNotNull($this->db->fetchOne('SELECT processed_at FROM integration_outbox WHERE id = ?', [$first]));
        self::assertNull($this->db->fetchOne('SELECT processed_at FROM integration_outbox WHERE id = ?', [$failed]));
        self::assertNull($this->db->fetchOne('SELECT processed_at FROM integration_outbox WHERE id = ?', [$later]));
        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM integration_deliveries WHERE message_id = ?', [$failed]));
        $this->db->update('integration_outbox', ['schema_version' => 1], ['id' => $failed]);
        self::assertSame(2, $processor->drain());
        self::assertSame(0, $processor->drain());
    }

    public function testStaleWritesAreRejectedAndAuditPaginationIsStable(): void
    {
        $group = $this->request('POST', '/api/ministries/ministries', ['name' => 'Welcome', 'type' => 'ministry'], 201);
        $member = $this->request('POST', '/api/ministries/memberships', ['ministryId' => $group['id'], 'personId' => $this->ids['adult'], 'joinedOn' => '2030-01-01', 'role' => 'member'], 201);
        $path = '/api/ministries/memberships/'.$member['id'].'/role';
        $leader = $this->request('POST', $path, ['role' => 'leader'], 200, ['HTTP_IF_MATCH' => '"1"']);
        self::assertSame(2, $leader['version']);
        $this->request('POST', $path, ['role' => 'member'], 409, ['HTTP_IF_MATCH' => '"1"']);
        $this->request('POST', $path, ['role' => 'member'], 422, ['HTTP_IF_MATCH' => '*']);
        $audit = $this->request('GET', '/api/workflows/ministries/'.$member['id'].'/audit?limit=1');
        self::assertSame(2, $audit['entries'][0]['version']);
        self::assertSame(2, $audit['nextBefore']);
        $older = $this->request('GET', '/api/workflows/ministries/'.$member['id'].'/audit?limit=1&before=2');
        self::assertSame(1, $older['entries'][0]['version']);
        self::assertNull($older['nextBefore']);
        self::assertSame(2, (int) $this->db->fetchOne('SELECT COUNT(*) FROM workflow_audit WHERE record_id = ?', [$member['id']]));
        self::getContainer()->set(AuthorizationCheckerInterface::class, new WorkflowTestAuthorization(false));
        $this->request('GET', '/api/workflows/ministries/'.$member['id'].'/audit', [], 403);
    }

    public function testLateWorkerCannotOverwriteAReconciledProviderOutcome(): void
    {
        $original = \Symfony\Component\Clock\Clock::get();
        $clock = new \Symfony\Component\Clock\MockClock('2030-01-06T09:00:00Z');
        \Symfony\Component\Clock\Clock::set($clock);
        try {
            $template = $this->request('POST', '/api/communications/templates', ['name' => 'Welcome', 'channel' => 'email', 'subject' => 'Welcome', 'body' => 'Hello'], 201);
            $this->request('POST', '/api/communications/preferences', ['personId' => $this->ids['adult'], 'channel' => 'email', 'consent' => true, 'consentNote' => 'Signed form'], 201);
            $campaign = $this->request('POST', '/api/communications/campaigns', ['name' => 'Welcome', 'templateId' => $template['id'], 'recipientIds' => [$this->ids['adult']]], 201);
            $this->request('POST', '/api/communications/campaigns/'.$campaign['id'].'/queue');
            $gateway = self::getContainer()->get(\App\Communications\Application\Port\MessageGateway::class);
            $gateway->fails = true;
            $gateway->duringSend = function (string $id) use ($clock): void {
                try {
                    self::getContainer()->get(\App\Communications\Application\Command\ResolveDelivery\ResolveDeliveryHandler::class)(new \App\Communications\Application\Command\ResolveDelivery\ResolveDeliveryCommand($id, 'delivered', 'Provider confirmed acceptance.'));
                    self::fail('Active deliveries must not be reconciled immediately.');
                } catch (DomainException $error) {
                    self::assertStringContainsString('in flight', $error->getMessage());
                }
                $clock->sleep(300);
                self::getContainer()->get(\App\Communications\Application\Command\ResolveDelivery\ResolveDeliveryHandler::class)(new \App\Communications\Application\Command\ResolveDelivery\ResolveDeliveryCommand($id, 'delivered', 'Provider confirmed acceptance after timeout.'));
            };
            self::assertTrue(self::getContainer()->get(\App\Communications\Application\Command\DeliverNextMessage\DeliverNextMessageHandler::class)(new \App\Communications\Application\Command\DeliverNextMessage\DeliverNextMessageCommand()));
            $delivery = $this->request('GET', '/api/communications')['deliveries'][0];
            self::assertSame('delivered', $delivery['status']);
            self::assertCount(1, $delivery['resolutions']);
            self::assertSame(3, $delivery['version']);
            self::assertNull($delivery['error']);
        } finally {
            \Symfony\Component\Clock\Clock::set($original);
        }
    }

    public function testMissingProviderKeepsDeliveryPendingAndFailedOutcomesCanBeReconciled(): void
    {
        $template = $this->request('POST', '/api/communications/templates', ['name' => 'Welcome', 'channel' => 'email', 'subject' => 'Welcome', 'body' => 'Hello'], 201);
        $this->request('POST', '/api/communications/preferences', ['personId' => $this->ids['adult'], 'channel' => 'email', 'consent' => true, 'consentNote' => 'Signed form'], 201);
        $campaign = $this->request('POST', '/api/communications/campaigns', ['name' => 'Welcome', 'templateId' => $template['id'], 'recipientIds' => [$this->ids['adult']]], 201);
        $this->request('POST', '/api/communications/campaigns/'.$campaign['id'].'/queue');
        $gateway = self::getContainer()->get(\App\Communications\Application\Port\MessageGateway::class);
        $gateway->configured = false;
        self::assertFalse(self::getContainer()->get(\App\Communications\Application\Command\DeliverNextMessage\DeliverNextMessageHandler::class)(new \App\Communications\Application\Command\DeliverNextMessage\DeliverNextMessageCommand()));
        $delivery = $this->request('GET', '/api/communications')['deliveries'][0];
        self::assertSame('pending', $delivery['status']);
        self::assertNull($delivery['attemptedAt']);
        $gateway->configured = true;
        $gateway->fails = true;
        self::assertTrue(self::getContainer()->get(\App\Communications\Application\Command\DeliverNextMessage\DeliverNextMessageHandler::class)(new \App\Communications\Application\Command\DeliverNextMessage\DeliverNextMessageCommand()));
        $delivery = $this->request('GET', '/api/communications')['deliveries'][0];
        self::assertSame('failed', $delivery['status']);
        self::assertNotNull($delivery['attemptedAt']);
        self::assertStringNotContainsString('credential', $delivery['error']);
        $resolved = $this->request('PUT', '/api/communications/deliveries/'.$delivery['id'].'/resolve', ['status' => 'delivered', 'note' => 'Provider confirmed acceptance by reference.'], 200, ['HTTP_IF_MATCH' => '"'.$delivery['version'].'"']);
        self::assertSame('delivered', $resolved['status']);
        self::assertCount(1, $resolved['resolutions']);
        self::assertFalse(self::getContainer()->get(\App\Communications\Application\Command\DeliverNextMessage\DeliverNextMessageHandler::class)(new \App\Communications\Application\Command\DeliverNextMessage\DeliverNextMessageCommand()));
        self::assertCount(0, $gateway->sent);
    }

    public function testEventCapacityWaitlistCancellationAndRejoiningPreserveOrder(): void
    {
        $o = $this->occurrence();
        $url = '/api/events/'.$o['eventId'].'/occurrences/'.$o['id'].'/registrations';
        self::assertFalse($this->request('GET', $url)['acceptingRegistrations']);
        $this->request('POST', $url, ['personId' => $this->ids['adult']], 409);
        $settings = $this->request('PUT', $url.'/settings', ['open' => true, 'capacity' => 1, 'expectedVersion' => 0]);
        self::assertSame(1, $settings['version']);
        $first = $this->request('POST', $url, ['personId' => $this->ids['adult']], 201);
        $second = $this->request('POST', $url, ['personId' => $this->ids['child']], 201);
        $third = $this->request('POST', $url, ['personId' => $this->ids['otherChild']], 201);
        self::assertSame('confirmed', $first['status']);
        self::assertSame('waitlisted', $second['status']);
        $this->request('POST', $url, ['personId' => $this->ids['adult']], 409);
        $this->request('POST', $url.'/'.$first['id'].'/cancel', ['reason' => 'Plans changed', 'expectedVersion' => 2], 409);
        $this->request('POST', $url.'/'.$first['id'].'/cancel', ['reason' => 'Plans changed', 'expectedVersion' => 1]);
        $overview = $this->request('GET', $url);
        self::assertSame(['confirmed' => 1, 'waitlisted' => 1, 'cancelled' => 1, 'pending_payment' => 0], $overview['counts']);
        $byPerson = array_column($overview['items'], null, 'personId');
        self::assertSame('confirmed', $byPerson[$this->ids['child']]['status']);
        self::assertSame('waitlisted', $byPerson[$this->ids['otherChild']]['status']);
        self::assertCount(1, $byPerson[$this->ids['child']]['history']);
        $rejoined = $this->request('POST', $url, ['personId' => $this->ids['adult']], 201);
        self::assertSame($first['id'], $rejoined['id']);
        self::assertSame('waitlisted', $rejoined['status']);
        self::assertNotSame($first['queueToken'], $rejoined['queueToken']);
        $this->request('POST', $url.'/'.$second['id'].'/cancel', ['reason' => 'Cannot attend', 'expectedVersion' => 2]);
        $overview = $this->request('GET', $url);
        $byPerson = array_column($overview['items'], null, 'personId');
        self::assertSame('confirmed', $byPerson[$this->ids['otherChild']]['status']);
        self::assertSame('waitlisted', $byPerson[$this->ids['adult']]['status']);
        self::assertSame(0, $overview['remaining']);
        self::assertSame('adult', $byPerson[$this->ids['adult']]['personName']);
        self::assertArrayNotHasKey('email', $byPerson[$this->ids['adult']]);
        self::assertSame(9, (int) $this->db->fetchOne("SELECT COUNT(*) FROM workflow_audit WHERE context = 'events'"));
        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM attendance_records'));
    }

    public function testRegistrationSettingsValidationStaleWritesAndCapacityIncrease(): void
    {
        $o = $this->occurrence();
        $url = '/api/events/'.$o['eventId'].'/occurrences/'.$o['id'].'/registrations';
        $this->request('PUT', $url.'/settings', ['open' => true, 'capacity' => 0, 'expectedVersion' => 0], 422);
        $this->request('PUT', $url.'/settings', ['open' => true, 'capacity' => 1, 'waitlistEnabled' => false, 'expectedVersion' => 0]);
        $this->request('POST', $url, ['personId' => $this->ids['adult']], 201);
        $this->request('POST', $url, ['personId' => $this->ids['child']], 409);
        $this->request('PUT', $url.'/settings', ['open' => true, 'capacity' => 1, 'expectedVersion' => 0], 409);
        $this->request('PUT', $url.'/settings', ['open' => true, 'capacity' => 1, 'waitlistEnabled' => true, 'expectedVersion' => 1]);
        $this->request('POST', $url, ['personId' => $this->ids['child']], 201);
        $this->request('PUT', $url.'/settings', ['open' => true, 'capacity' => 2, 'expectedVersion' => 2]);
        self::assertSame(2, $this->request('GET', $url)['counts']['confirmed']);
        $this->request('PUT', $url.'/settings', ['open' => true, 'capacity' => 1, 'expectedVersion' => 3], 409);
        $this->request('PUT', $url.'/settings', ['open' => false, 'capacity' => 2, 'expectedVersion' => 3]);
        $this->request('POST', $url, ['personId' => $this->ids['otherChild']], 409);
        $this->request('PUT', $url.'/settings', ['open' => true, 'capacity' => null, 'expectedVersion' => 4]);
        $this->request('POST', $url, ['personId' => $this->ids['otherChild']], 201);
        self::assertNull($this->request('GET', $url)['remaining']);
    }

    public function testRegistrationRosterPaginationAndOccurrenceIsolation(): void
    {
        $o = $this->occurrence();
        $url = '/api/events/'.$o['eventId'].'/occurrences/'.$o['id'].'/registrations';
        $this->request('PUT', $url.'/settings', ['open' => true, 'expectedVersion' => 0]);
        foreach (['adult', 'child', 'otherChild'] as $person) {
            $this->request('POST', $url, ['personId' => $this->ids[$person]], 201);
        }
        $first = $this->request('GET', $url.'?limit=2');
        self::assertCount(2, $first['items']);
        self::assertNotNull($first['nextAfter']);
        $last = $this->request('GET', $url.'?limit=2&after='.$first['nextAfter']);
        self::assertCount(1, $last['items']);
        self::assertNull($last['nextAfter']);
        self::assertCount(3, array_unique(array_column([...$first['items'], ...$last['items']], 'id')));
        $this->request('GET', $url.'?limit=101', [], 422);
        $this->request('POST', $url, ['personId' => Uuid::v7()->toRfc4122()], 422);
        $other = $this->occurrence();
        $otherUrl = '/api/events/'.$other['eventId'].'/occurrences/'.$other['id'].'/registrations';
        $this->request('POST', $otherUrl.'/'.$first['items'][0]['id'].'/cancel', ['reason' => 'Wrong occurrence', 'expectedVersion' => 1], 404);
        $this->request('GET', '/api/events/'.$other['eventId'].'/occurrences/'.$o['id'].'/registrations', [], 404);
    }

    public function testCancelledAndEndedOccurrencesCloseRegistrationWithoutChangingHistory(): void
    {
        $o = $this->occurrence();
        $url = '/api/events/'.$o['eventId'].'/occurrences/'.$o['id'].'/registrations';
        $this->request('PUT', $url.'/settings', ['open' => true, 'capacity' => 1, 'expectedVersion' => 0]);
        $first = $this->request('POST', $url, ['personId' => $this->ids['adult']], 201);
        $this->request('POST', $url, ['personId' => $this->ids['child']], 201);
        $this->request('PUT', '/api/events/'.$o['eventId'].'/occurrences/'.$o['id'], ['startsAt' => '2030-01-06T09:00', 'endsAt' => '2030-01-06T12:00', 'location' => 'Church', 'status' => 'cancelled'], 204);
        self::getContainer()->get(\App\Shared\Infrastructure\Outbox\OutboxProcessor::class)->drainAll();
        self::assertFalse($this->request('GET', $url)['acceptingRegistrations']);
        $this->request('POST', $url, ['personId' => $this->ids['otherChild']], 409);
        $this->request('POST', $url.'/'.$first['id'].'/cancel', ['reason' => 'Event cancelled', 'expectedVersion' => 1]);
        self::assertSame(1, $this->request('GET', $url)['counts']['waitlisted']);
        $this->request('PUT', $url.'/settings', ['open' => true, 'expectedVersion' => 1], 409);
        $this->request('PUT', '/api/events/'.$o['eventId'].'/occurrences/'.$o['id'], ['startsAt' => '2030-01-06T09:00', 'endsAt' => '2030-01-06T12:00', 'location' => 'Church', 'status' => 'scheduled'], 204);
        self::getContainer()->get(\App\Shared\Infrastructure\Outbox\OutboxProcessor::class)->drainAll();
        self::getContainer()->get(\Symfony\Component\Clock\ClockInterface::class)->modify('+1 day');
        self::assertFalse($this->request('GET', $url)['acceptingRegistrations']);
        $this->request('POST', $url, ['personId' => $this->ids['otherChild']], 409);
    }

    public function testWaitlistUsesDatabaseQueueOrderRatherThanUuidOrder(): void
    {
        $o = $this->occurrence();
        $url = '/api/events/'.$o['eventId'].'/occurrences/'.$o['id'].'/registrations';
        $this->request('PUT', $url.'/settings', ['open' => true, 'capacity' => 1, 'expectedVersion' => 0]);
        $confirmed = $this->request('POST', $url, ['personId' => $this->ids['adult']], 201);
        $repository = self::getContainer()->get(\App\Events\Domain\Repository\EventRegistrationRepository::class);
        self::getContainer()->get(\App\Shared\Application\WorkflowTransaction::class)->run(function () use ($repository, $o) {
            $repository->save(new \App\Events\Domain\Model\EventRegistration($o['id'], $this->ids['child'], 'waitlisted', 'ffffffff-ffff-7fff-bfff-ffffffffffff'));
            $repository->save(new \App\Events\Domain\Model\EventRegistration($o['id'], $this->ids['otherChild'], 'waitlisted', '00000000-0000-7000-8000-000000000000'));
        });
        $this->request('POST', $url.'/'.$confirmed['id'].'/cancel', ['reason' => 'Cannot attend', 'expectedVersion' => 1]);
        $items = $this->request('GET', $url)['items'];
        $byPerson = array_column($items, null, 'personId');
        self::assertSame('confirmed', $byPerson[$this->ids['child']]['status']);
        self::assertSame('waitlisted', $byPerson[$this->ids['otherChild']]['status']);
        self::assertSame([1, 2, 3], array_column($items, 'queuePosition'));
        $this->request('GET', $url.'?after='.Uuid::v7()->toRfc4122(), [], 422);
    }

    public function testEventRegistrationPermissionsAreRequiredBeforeDataAccess(): void
    {
        $o = $this->occurrence();
        $url = '/api/events/'.$o['eventId'].'/occurrences/'.$o['id'].'/registrations';
        $authorization = self::getContainer()->get(AuthorizationCheckerInterface::class);
        $authorization->allowed = false;
        $this->request('GET', $url, [], 403);
        $this->request('POST', $url, ['personId' => $this->ids['adult']], 403);
        $this->request('PUT', $url.'/settings', ['open' => true, 'expectedVersion' => 0], 403);
        $authorization->allowed = true;
        $authorization->permissions = ['events.read'];
        $this->request('GET', $url);
        $this->request('POST', $url, ['personId' => $this->ids['adult']], 403);
        $this->request('PUT', $url.'/settings', ['open' => true, 'expectedVersion' => 0], 403);
    }

    private function formPayload(?array $o = null): array
    {
        return ['title' => 'Welcome request', 'description' => 'Tell us about yourself', 'fields' => [['key' => 'name', 'label' => 'Your name', 'type' => 'text', 'required' => true], ['key' => 'email', 'label' => 'Email address', 'type' => 'email', 'required' => true], ['key' => 'interest', 'label' => 'Interest', 'type' => 'select', 'required' => true, 'options' => ['Visit', 'Join']], ['key' => 'agreement', 'label' => 'Please review my request', 'type' => 'checkbox', 'required' => true]], 'eventId' => $o['eventId'] ?? null, 'occurrenceId' => $o['id'] ?? null];
    }

    public function testPublicEventFormDisplaysOnlyItsLinkedOccurrenceAndPublicEventFields(): void
    {
        $occurrence = $this->occurrence();
        $form = $this->request('POST', '/api/forms', $this->formPayload($occurrence), 201);
        $url = '/api/public/forms/'.$form['publicToken'];
        $this->request('GET', $url, [], 404);
        $this->request('PUT', '/api/forms/'.$form['id'].'/publication', ['published' => true, 'expectedVersion' => $form['version']]);
        self::getContainer()->get(AuthorizationCheckerInterface::class)->permissions = [];
        $page = $this->request('GET', $url);
        self::assertTrue($page['isEventRegistration']);
        self::assertSame(['title', 'description', 'timezone', 'startsAt', 'endsAt', 'location', 'status'], array_keys($page['event']));
        self::assertSame($occurrence['startsAt'], $page['event']['startsAt']);
        self::assertSame($occurrence['location'], $page['event']['location']);
        self::assertArrayNotHasKey('publicToken', $page);
        self::assertArrayNotHasKey('eventId', $page['event']);
        self::assertArrayNotHasKey('registrations', $page['event']);
        self::getContainer()->get(AuthorizationCheckerInterface::class)->permissions = null;
        $this->request('PUT', '/api/forms/'.$form['id'].'/publication', ['published' => false, 'expectedVersion' => $form['version'] + 1]);
        $this->request('GET', $url, [], 404);
    }

    private function publicResponse(array $form): array
    {
        $page = $this->request('GET', '/api/public/forms/'.$form['publicToken']);
        self::assertArrayNotHasKey('publicToken', $page);
        self::assertArrayNotHasKey('id', $page);
        self::getContainer()->get(\Symfony\Component\Clock\ClockInterface::class)->modify('+3 seconds');

        return ['answers' => ['name' => 'New visitor', 'email' => 'visitor@example.test', 'interest' => 'Visit', 'agreement' => true], 'formVersion' => $page['version'], 'challenge' => $page['challenge'], 'submissionKey' => Uuid::v7()->toRfc4122()];
    }

    public function testPublicFormsPublicationValidationIdempotencyAndReviewSnapshots(): void
    {
        $payload = $this->formPayload();
        $form = $this->request('POST', '/api/forms', $payload, 201);
        $publicUrl = '/api/public/forms/'.$form['publicToken'];
        $this->request('GET', $publicUrl, [], 404);
        $form = $this->request('PUT', '/api/forms/'.$form['id'].'/publication', ['published' => true, 'expectedVersion' => 1]);
        $response = $this->publicResponse($form);
        $bad = $response;
        $bad['answers']['email'] = 'invalid';
        $this->request('POST', $publicUrl, $bad, 422);
        $bad = $response;
        $bad['answers']['interest'] = 'Other';
        $this->request('POST', $publicUrl, $bad, 422);
        $bad = $response;
        $bad['answers']['agreement'] = false;
        $this->request('POST', $publicUrl, $bad, 422);
        $bad = $response;
        $bad['answers']['unknown'] = 'Extra';
        $this->request('POST', $publicUrl, $bad, 422);
        $received = $this->request('POST', $publicUrl, $response, 202);
        self::assertSame(['message' => 'Your response has been received for staff review.'], $received);
        $this->request('POST', $publicUrl, $response, 202);
        $bad = $response;
        $bad['answers']['name'] = 'Changed';
        $this->request('POST', $publicUrl, $bad, 409);
        $this->request('PUT', '/api/forms/'.$form['id'], $payload + ['expectedVersion' => 2], 409);
        $this->request('PUT', '/api/forms/'.$form['id'].'/publication', ['published' => false, 'expectedVersion' => 2]);
        $this->request('POST', $publicUrl, $response, 404);
        $payload['title'] = 'Changed form';
        $payload['fields'][0]['label'] = 'New wording';
        $this->request('PUT', '/api/forms/'.$form['id'], $payload + ['expectedVersion' => 3]);
        $page = $this->request('GET', '/api/forms/'.$form['id'].'/submissions');
        self::assertCount(1, $page['items']);
        $submission = $page['items'][0];
        self::assertSame('Welcome request', $submission['snapshot']['title']);
        self::assertSame('Your name', $submission['snapshot']['fields'][0]['label']);
        self::assertSame('pending', $submission['status']);
        $url = '/api/forms/submissions/'.$submission['id'].'/review';
        $this->request('PUT', $url, ['status' => 'approved', 'note' => 'Identity checked', 'personId' => $this->ids['adult'], 'expectedVersion' => 2], 409);
        $review = $this->request('PUT', $url, ['status' => 'approved', 'note' => 'Identity checked', 'personId' => $this->ids['adult'], 'expectedVersion' => 1]);
        self::assertSame($this->ids['adult'], $review['personId']);
        self::assertSame('approved', $review['status']);
        $this->request('PUT', $url, ['status' => 'rejected', 'note' => 'Changed mind', 'expectedVersion' => 2], 409);
        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM event_registrations'));
    }

    public function testFormSpamProtectionAndRateLimiting(): void
    {
        $form = $this->request('POST', '/api/forms', $this->formPayload(), 201);
        $form = $this->request('PUT', '/api/forms/'.$form['id'].'/publication', ['published' => true, 'expectedVersion' => 1]);
        $url = '/api/public/forms/'.$form['publicToken'];
        $page = $this->request('GET', $url);
        $response = ['answers' => [], 'formVersion' => $page['version'], 'challenge' => $page['challenge'], 'submissionKey' => Uuid::v7()->toRfc4122()];
        $this->request('POST', $url, $response, 422);
        // Too fast.
        self::getContainer()->get(\Symfony\Component\Clock\ClockInterface::class)->modify('+3 seconds');
        $this->request('POST', $url, $response + ['website' => 'spam.example'], 422);
        $bad = $response;
        $bad['challenge'] = str_repeat('0', 100);
        $this->request('POST', $url, $bad, 422);
        self::getContainer()->get(\Symfony\Component\Clock\ClockInterface::class)->modify('+3 hours');
        $this->request('POST', $url, $response, 422);
        $this->request('POST', $url, ['answers' => []], 422);
        // Malformed payload also consumes its IP limit.
        for ($i = 0; $i < 5; ++$i) {
            $this->request('POST', $url, $bad, 422);
        }
        $this->request('POST', $url, $bad, 429);
        self::assertSame(0, (int) $this->db->fetchOne("SELECT COUNT(*) FROM forms_records WHERE kind='submissions'"));
    }

    public function testApprovedFormEventBookingIsAtomicAndRespectsCapacity(): void
    {
        $o = $this->occurrence();
        $booking = '/api/events/'.$o['eventId'].'/occurrences/'.$o['id'].'/registrations';
        $this->request('PUT', $booking.'/settings', ['open' => true, 'capacity' => 1, 'expectedVersion' => 0]);
        $this->request('POST', $booking, ['personId' => $this->ids['adult']], 201);
        $form = $this->request('POST', '/api/forms', $this->formPayload($o), 201);
        $form = $this->request('PUT', '/api/forms/'.$form['id'].'/publication', ['published' => true, 'expectedVersion' => 1]);
        $this->request('POST', '/api/public/forms/'.$form['publicToken'], $this->publicResponse($form), 202);
        $submission = $this->request('GET', '/api/forms/'.$form['id'].'/submissions')['items'][0];
        $url = '/api/forms/submissions/'.$submission['id'].'/review';
        $this->request('PUT', $url, ['status' => 'approved', 'note' => 'Reviewed', 'expectedVersion' => 1], 422);
        $this->request('PUT', $url, ['status' => 'approved', 'note' => 'Reviewed', 'personId' => $this->ids['adult'], 'expectedVersion' => 1], 409);
        self::assertSame('pending', $this->request('GET', '/api/forms/'.$form['id'].'/submissions')['items'][0]['status']);
        $this->db->executeStatement("CREATE TRIGGER reject_review BEFORE UPDATE ON forms_records WHEN NEW.kind='submissions' BEGIN SELECT RAISE(ABORT,'test failure'); END");
        try {
            self::getContainer()->get(\App\Forms\Application\Command\ReviewSubmission\ReviewSubmissionHandler::class)(new \App\Forms\Application\Command\ReviewSubmission\ReviewSubmissionCommand($submission['id'], 'approved', 'Checked identity', $this->ids['child'], 1));
            self::fail('Expected a failed review write.');
        } catch (\Doctrine\DBAL\Exception) {
        }
        self::assertSame(1, (int) $this->db->fetchOne('SELECT COUNT(*) FROM event_registrations'));
        $this->db->executeStatement('DROP TRIGGER reject_review');
        $approved = $this->request('PUT', $url, ['status' => 'approved', 'note' => 'Checked identity', 'personId' => $this->ids['child'], 'expectedVersion' => 1]);
        self::assertNotNull($approved['registrationId']);
        $roster = $this->request('GET', $booking);
        self::assertSame(1, $roster['counts']['waitlisted']);
        self::assertSame($approved['registrationId'], $roster['items'][1]['id']);
    }

    public function testFormsPermissionsAndPagination(): void
    {
        $first = $this->request('POST', '/api/forms', $this->formPayload(), 201);
        $this->request('POST', '/api/forms', $this->formPayload(), 201);
        $page = $this->request('GET', '/api/forms?limit=1');
        self::assertCount(1, $page['items']);
        self::assertNotNull($page['nextAfter']);
        self::assertNull($this->request('GET', '/api/forms?limit=1&after='.$page['nextAfter'])['nextAfter']);
        $authorization = self::getContainer()->get(AuthorizationCheckerInterface::class);
        $authorization->permissions = ['forms.read'];
        $this->request('GET', '/api/forms');
        $this->request('POST', '/api/forms', $this->formPayload(), 403);
        $this->request('GET', '/api/forms/'.$first['id'].'/submissions', [], 403);
        $authorization->permissions = [];
        $this->request('GET', '/api/forms', [], 403);
    }

    public function testFormDefinitionsRejectInvalidSchemasAndStalePublicRevisions(): void
    {
        $payload = $this->formPayload();
        $payload['fields'][1]['key'] = 'name';
        $this->request('POST', '/api/forms', $payload, 422);
        $payload = $this->formPayload();
        $payload['fields'][0]['type'] = 'html';
        $this->request('POST', '/api/forms', $payload, 422);
        $payload = $this->formPayload();
        $payload['fields'][2]['options'] = [123];
        $this->request('POST', '/api/forms', $payload, 422);
        $form = $this->request('POST', '/api/forms', $this->formPayload(), 201);
        $form = $this->request('PUT', '/api/forms/'.$form['id'].'/publication', ['published' => true, 'expectedVersion' => 1]);
        $response = $this->publicResponse($form);
        $this->request('PUT', '/api/forms/'.$form['id'].'/publication', ['published' => false, 'expectedVersion' => 2]);
        $this->request('PUT', '/api/forms/'.$form['id'].'/publication', ['published' => true, 'expectedVersion' => 3]);
        $this->request('POST', '/api/public/forms/'.$form['publicToken'], $response, 409);
        $this->request('GET', '/api/forms?limit=101', [], 422);
    }

    public function testEventFormReviewRequiresPeopleAndEventPermissions(): void
    {
        $o = $this->occurrence();
        $form = $this->request('POST', '/api/forms', $this->formPayload($o), 201);
        $form = $this->request('PUT', '/api/forms/'.$form['id'].'/publication', ['published' => true, 'expectedVersion' => 1]);
        $this->request('POST', '/api/public/forms/'.$form['publicToken'], $this->publicResponse($form), 202);
        $submission = $this->request('GET', '/api/forms/'.$form['id'].'/submissions')['items'][0];
        $authorization = self::getContainer()->get(AuthorizationCheckerInterface::class);
        $authorization->permissions = ['forms.manage'];
        $review = ['status' => 'approved', 'note' => 'Checked', 'personId' => $this->ids['adult'], 'expectedVersion' => 1];
        $url = '/api/forms/submissions/'.$submission['id'].'/review';
        $this->request('PUT', $url, $review, 403);
        $authorization->permissions = ['forms.manage', 'people.read'];
        $this->request('PUT', $url, $review, 403);
        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM event_registrations'));
        $rejected = $this->request('PUT', $url, ['status' => 'rejected', 'note' => 'Cannot fulfil request', 'expectedVersion' => 1]);
        self::assertSame('rejected', $rejected['status']);
        self::assertNull($rejected['personId']);
        self::assertCount(2, $this->request('GET', '/api/workflows/forms/'.$submission['id'].'/audit')['entries']);
    }

    public function testGivingStatementsSplitFundsCurrenciesAndUseReversalDates(): void
    {
        $a = $this->request('POST', '/api/giving/funds', ['name' => 'General'], 201);
        $b = $this->request('POST', '/api/giving/funds', ['name' => '=Risky fund'], 201);
        $gift = $this->request('POST', '/api/giving/gifts', ['amountMinor' => 10000, 'currency' => 'ZAR', 'allocations' => [['fundId' => $a['id'], 'amountMinor' => 6000], ['fundId' => $b['id'], 'amountMinor' => 4000]], 'receivedOn' => '2030-01-01', 'method' => 'bank', 'idempotencyKey' => 'report-gift', 'donorId' => $this->ids['adult']], 201);
        $this->request('POST', '/api/giving/gifts', ['amountMinor' => 1234, 'currency' => 'USD', 'allocations' => [['fundId' => $a['id'], 'amountMinor' => 1234]], 'receivedOn' => '2030-01-01', 'method' => 'cash', 'idempotencyKey' => 'report-usd', 'donorId' => null], 201);
        $this->request('POST', '/api/giving/gifts/'.$gift['id'].'/reverse', ['reversedOn' => '2030-02-01', 'reason' => 'Correction'], 201);
        $url = '/api/giving/reports?from=2030-01-01&to=2030-01-31';
        $all = $this->request('GET', $url);
        self::assertCount(2, $all['totals']);
        $statement = $this->request('GET', $url.'&donorId='.$this->ids['adult']);
        self::assertSame('adult', $statement['donor']['name']);
        self::assertArrayNotHasKey('email', $statement['donor']);
        self::assertSame(10000, $statement['totals'][0]['netAmountMinor']);
        self::assertSame([4000, 6000], array_values(array_unique((function ($rows) {
            $amounts = array_column($rows, 'netAmountMinor');
            sort($amounts);

            return $amounts;
        })($statement['fundTotals']))));
        $feb = $this->request('GET', '/api/giving/reports?from=2030-02-01&to=2030-02-28&donorId='.$this->ids['adult']);
        self::assertSame(-10000, $feb['totals'][0]['netAmountMinor']);
        self::assertSame(0, $feb['totals'][0]['grossAmountMinor']);
        $fund = $this->request('GET', $url.'&fundId='.$b['id']);
        self::assertSame(4000, $fund['totals'][0]['netAmountMinor']);
        $this->client->request('GET', '/api/giving/reports/export?from=2030-01-01&to=2030-02-28');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('text/csv', $this->client->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString("'=Risky fund", $this->client->getResponse()->getContent());
        self::assertStringContainsString('-60.00', $this->client->getResponse()->getContent());
        $this->request('GET', '/api/giving/reports?from=2030-02-01&to=2030-01-01', [], 422);
        $this->request('GET', '/api/giving/reports?from=2028-01-01&to=2030-01-01', [], 422);
        $authorization = self::getContainer()->get(AuthorizationCheckerInterface::class);
        $authorization->allowed = false;
        $this->request('GET', $url, [], 403);
        $this->request('GET', '/api/giving/pledge-progress?asOf=2030-01-01', [], 403);
    }

    public function testExplicitPledgeAssignmentsEnforceAvailableAmountsAndRetainCorrections(): void
    {
        $fund = $this->request('POST', '/api/giving/funds', ['name' => 'Building'], 201);
        $gift = $this->request('POST', '/api/giving/gifts', ['amountMinor' => 6000, 'currency' => 'ZAR', 'allocations' => [['fundId' => $fund['id'], 'amountMinor' => 6000]], 'receivedOn' => '2030-01-01', 'method' => 'bank', 'idempotencyKey' => 'pledge-gift', 'donorId' => $this->ids['adult']], 201);
        $pledge = $this->request('POST', '/api/giving/pledges', ['donorId' => $this->ids['adult'], 'fundId' => $fund['id'], 'amountMinor' => 10000, 'currency' => 'ZAR', 'dueOn' => '2030-01-05'], 201);
        $payload = ['pledgeId' => $pledge['id'], 'giftId' => $gift['id'], 'amountMinor' => 5000, 'reference' => 'explicit-match'];
        $link = $this->request('POST', '/api/giving/pledge-allocations', $payload, 201);
        self::assertSame($link['id'], $this->request('POST', '/api/giving/pledge-allocations', $payload, 201)['id']);
        $bad = $payload;
        $bad['reference'] = 'over-allocation';
        $bad['amountMinor'] = 2000;
        $this->request('POST', '/api/giving/pledge-allocations', $bad, 409);
        $progress = $this->request('GET', '/api/giving/pledge-progress?asOf=2030-01-06')['items'][0];
        self::assertSame(5000, $progress['appliedAmountMinor']);
        self::assertSame(5000, $progress['remainingAmountMinor']);
        self::assertTrue($progress['overdue']);
        self::assertSame('open', $progress['status']);
        $this->request('POST', '/api/giving/pledge-allocations/'.$link['id'].'/cancel', ['expectedVersion' => 2, 'reason' => 'Wrong pledge'], 409);
        $this->request('POST', '/api/giving/pledge-allocations/'.$link['id'].'/cancel', ['expectedVersion' => 1, 'reason' => 'Wrong pledge']);
        self::assertSame(0, $this->request('GET', '/api/giving/pledge-progress?asOf=2030-01-06')['items'][0]['appliedAmountMinor']);
        $payload['reference'] = 'corrected-match';
        $payload['amountMinor'] = 6000;
        $this->request('POST', '/api/giving/pledge-allocations', $payload, 201);
        $this->request('POST', '/api/giving/gifts/'.$gift['id'].'/reverse', ['reversedOn' => '2030-02-01', 'reason' => 'Gift reversed'], 201);
        self::assertSame(6000, $this->request('GET', '/api/giving/pledge-progress?asOf=2030-01-31')['items'][0]['appliedAmountMinor']);
        self::assertSame(0, $this->request('GET', '/api/giving/pledge-progress?asOf=2030-02-01')['items'][0]['appliedAmountMinor']);
        $payload['reference'] = 'reversed-gift';
        $payload['amountMinor'] = 1;
        $this->request('POST', '/api/giving/pledge-allocations', $payload, 409);
    }

    public function testPledgeAssignmentsRejectDifferentDonorsCurrenciesFundsAndPledgeOverfill(): void
    {
        $fund = $this->request('POST', '/api/giving/funds', ['name' => 'General'], 201);
        $other = $this->request('POST', '/api/giving/funds', ['name' => 'Other'], 201);
        $gift = $this->request('POST', '/api/giving/gifts', ['amountMinor' => 5000, 'currency' => 'ZAR', 'allocations' => [['fundId' => $fund['id'], 'amountMinor' => 5000]], 'receivedOn' => '2030-01-01', 'method' => 'bank', 'idempotencyKey' => 'mismatch-gift', 'donorId' => $this->ids['adult']], 201);
        foreach ([[$this->ids['child'], $fund['id'], 'ZAR'], [$this->ids['adult'], $fund['id'], 'USD'], [$this->ids['adult'], $other['id'], 'ZAR']] as [$donor, $fundId, $currency]) {
            $pledge = $this->request('POST', '/api/giving/pledges', ['donorId' => $donor, 'fundId' => $fundId, 'currency' => $currency, 'amountMinor' => 1000, 'dueOn' => '2030-02-01'], 201);
            $this->request('POST', '/api/giving/pledge-allocations', ['pledgeId' => $pledge['id'], 'giftId' => $gift['id'], 'amountMinor' => 1, 'reference' => $pledge['id']], 409);
        }
        $pledge = $this->request('POST', '/api/giving/pledges', ['donorId' => $this->ids['adult'], 'fundId' => $fund['id'], 'currency' => 'ZAR', 'amountMinor' => 1000, 'dueOn' => '2030-02-01'], 201);
        $this->request('POST', '/api/giving/pledge-allocations', ['pledgeId' => $pledge['id'], 'giftId' => $gift['id'], 'amountMinor' => 1001, 'reference' => 'pledge-overfill'], 409);
        foreach (['JPY', 'BHD'] as $currency) {
            $this->request('POST', '/api/giving/gifts', ['amountMinor' => 1234, 'currency' => $currency, 'allocations' => [['fundId' => $fund['id'], 'amountMinor' => 1234]], 'receivedOn' => '2030-01-01', 'method' => 'cash', 'idempotencyKey' => $currency], 201);
        }
        $this->client->request('GET', '/api/giving/reports/export?from=2030-01-01&to=2030-01-01');
        self::assertResponseIsSuccessful();
        $csv = $this->client->getResponse()->getContent();
        self::assertStringContainsString('BHD,1234,1.234', $csv);
        self::assertStringContainsString('JPY,1234,1234', $csv);
    }

    private function groupFixture(): array
    {
        $group = $this->request('POST', '/api/ministries/ministries', ['name' => 'Home group', 'type' => 'small_group'], 201);
        $leader = $this->request('POST', '/api/ministries/memberships', ['ministryId' => $group['id'], 'personId' => $this->ids['adult'], 'joinedOn' => '2030-01-01', 'role' => 'leader'], 201);
        $this->request('POST', '/api/ministries/memberships', ['ministryId' => $group['id'], 'personId' => $this->ids['child'], 'joinedOn' => '2030-01-01'], 201);
        $o = $this->occurrence();
        $meeting = $this->request('POST', '/api/groups/'.$group['id'].'/meetings', ['occurrenceId' => $o['id']], 201);

        return compact('group', 'leader', 'o', 'meeting');
    }

    private function useGroupLeader(): void
    {
        $account = \App\IdentityAccess\Domain\Model\UserAccount::create(\App\IdentityAccess\Domain\ValueObject\UserId::generate(), \App\IdentityAccess\Domain\ValueObject\Username::fromString('group-leader'), \App\IdentityAccess\Domain\ValueObject\PasswordHash::fromString('hash'), $this->ids['adult']);
        $token = new \Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken(new \App\IdentityAccess\Infrastructure\Security\SymfonyUser($account), 'main', ['ROLE_USER']);
        self::getContainer()->get(\Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface::class)->setToken($token);
        // Audit actor references the active account; test table has no foreign key.
        self::getContainer()->get(AuthorizationCheckerInterface::class)->permissions = ['ministries.lead'];
    }

    public function testGroupMeetingAttendanceUsesOwnerCorrectionsAndKeepsClosedHistory(): void
    {
        $data = $this->groupFixture();
        $id = $data['group']['id'];
        $meeting = $data['meeting'];
        $this->request('POST', '/api/attendance/entries', ['occurrenceId' => $data['o']['id'], 'personId' => $this->ids['collector'], 'status' => 'present'], 201);
        $url = '/api/groups/'.$id.'/meetings/'.$meeting['id'];
        $view = $this->request('GET', '/api/groups/'.$id);
        self::assertCount(2, $view['meetings'][0]['roster']);
        self::assertNotContains($this->ids['collector'], array_column($view['meetings'][0]['roster'], 'personId'));
        self::assertArrayNotHasKey('email', $view['members'][0]);
        $entry = $this->request('POST', $url.'/attendance', ['personId' => $this->ids['child'], 'status' => 'present']);
        $this->request('POST', $url.'/attendance', ['personId' => $this->ids['child'], 'status' => 'absent'], 409);
        $changed = $this->request('POST', $url.'/attendance', ['personId' => $this->ids['child'], 'status' => 'absent', 'expectedVersion' => 1, 'reason' => 'Entered incorrectly']);
        self::assertCount(1, $changed['corrections']);
        $this->request('POST', $url.'/cancel', ['expectedVersion' => 1, 'reason' => 'No gathering'], 409);
        $this->request('POST', $url.'/cancel', ['expectedVersion' => 2, 'reason' => 'No gathering']);
        $this->request('POST', $url.'/attendance', ['personId' => $this->ids['adult'], 'status' => 'present'], 409);
        $this->request('POST', $url.'/attendance', ['personId' => $this->ids['child'], 'status' => 'present', 'expectedVersion' => 2, 'reason' => 'Verified presence']);
        self::assertSame(2, (int) $this->db->fetchOne("SELECT COUNT(*) FROM attendance_records WHERE kind='entries'"));
        self::assertSame('scheduled', $this->request('GET', '/api/events/'.$data['o']['eventId'])['occurrences'][0]['status']);
    }

    public function testGroupLeadersAreScopedAndLeadershipRemovalRevokesAccess(): void
    {
        $data = $this->groupFixture();
        $id = $data['group']['id'];
        $other = $this->request('POST', '/api/ministries/ministries', ['name' => 'Other group'], 201);
        $this->useGroupLeader();
        self::assertSame([$id], array_column($this->request('GET', '/api/groups')['items'], 'id'));
        $this->request('GET', '/api/groups/'.$id);
        $this->request('GET', '/api/groups/'.$other['id'], [], 403);
        $this->request('POST', '/api/groups/'.$id.'/meetings', ['occurrenceId' => $data['o']['id']], 403);
        $this->request('GET', '/api/ministries', [], 403);
        $this->request('GET', '/api/attendance', [], 403);
        $url = '/api/groups/'.$id.'/meetings/'.$data['meeting']['id'].'/attendance';
        $this->request('POST', $url, ['personId' => $this->ids['collector'], 'status' => 'present'], 422);
        $this->request('POST', $url, ['personId' => $this->ids['child'], 'status' => 'present']);
        $repository = self::getContainer()->get(\App\Ministries\Domain\Repository\MinistriesRepository::class);
        $repository->save($repository->find('memberships', $data['leader']['id'])->changeRole('member'));
        self::assertCount(0, $this->request('GET', '/api/groups')['items']);
        $this->request('GET', '/api/groups/'.$id, [], 403);
        $this->request('POST', $url, ['personId' => $this->ids['child'], 'status' => 'absent', 'expectedVersion' => 1, 'reason' => 'Attempt after removal'], 403);
    }

    public function testGroupOccurrenceOwnershipAndReschedulingPreserveAttendance(): void
    {
        $data = $this->groupFixture();
        $o = $data['o'];
        $id = $data['group']['id'];
        $other = $this->request('POST', '/api/ministries/ministries', ['name' => 'Other'], 201);
        $this->request('POST', '/api/groups/'.$other['id'].'/meetings', ['occurrenceId' => $o['id']], 409);
        $url = '/api/groups/'.$id.'/meetings/'.$data['meeting']['id'].'/attendance';
        $this->request('POST', $url, ['personId' => $this->ids['child'], 'status' => 'present']);
        $this->request('PUT', '/api/events/'.$o['eventId'].'/occurrences/'.$o['id'], ['startsAt' => '2029-12-30T09:00', 'endsAt' => '2029-12-30T12:00', 'location' => 'Church', 'status' => 'cancelled'], 204);
        self::getContainer()->get(\App\Shared\Infrastructure\Outbox\OutboxProcessor::class)->drainAll();
        $view = $this->request('GET', '/api/groups/'.$id);
        self::assertCount(1, $view['meetings'][0]['roster']);
        self::assertSame($this->ids['child'], $view['meetings'][0]['roster'][0]['personId']);
        self::assertSame(1, $view['meetings'][0]['counts']['present']);
        $this->request('POST', $url, ['personId' => $this->ids['adult'], 'status' => 'present'], 422);
    }

    public function testScopedGroupAttendanceProtectsChildPresenceAndCustodyPrivacy(): void
    {
        $data = $this->groupFixture();
        $children = self::getContainer()->get(\App\ChildrenMinistry\Domain\Repository\ChildrenMinistryRepository::class);
        $session = new \App\ChildrenMinistry\Domain\Model\MinistrySession(Uuid::v7()->toRfc4122(), $data['o']['id'], [$this->ids['adult']]);
        $children->save($session);
        $children->save(new \App\ChildrenMinistry\Domain\Model\ChildCheckIn($session->id, $this->ids['child'], '2030-01-06T09:00:00Z', $this->ids['adult']));
        $this->useGroupLeader();
        $view = $this->request('GET', '/api/groups/'.$data['group']['id']);
        $child = array_values(array_filter($view['meetings'][0]['roster'], fn ($r) => $r['personId'] === $this->ids['child']))[0];
        self::assertTrue($child['childPresent']);
        self::assertArrayNotHasKey('checkedInBy', $child);
        self::assertArrayNotHasKey('collectorId', $child);
        self::assertSame(1, $view['meetings'][0]['counts']['present']);
        $url = '/api/groups/'.$data['group']['id'].'/meetings/'.$data['meeting']['id'].'/attendance';
        $this->request('POST', $url, ['personId' => $this->ids['child'], 'status' => 'absent'], 409);
        $this->request('POST', $url, ['personId' => $this->ids['child'], 'status' => 'present']);
        self::assertSame(1, $this->request('GET', '/api/groups/'.$data['group']['id'])['meetings'][0]['counts']['present']);
    }

    public function testArchivedGroupsDisabledAccountsAndUnlinkedAccountsCannotUseLeaderScope(): void
    {
        $data = $this->groupFixture();
        $this->useGroupLeader();
        $storage = self::getContainer()->get(\Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface::class);
        $storage->getToken()->getUser()->account()->disable();
        self::assertCount(0, $this->request('GET', '/api/groups')['items']);
        $this->request('GET', '/api/groups/'.$data['group']['id'], [], 403);
        $this->useGroupLeader();
        $repository = self::getContainer()->get(\App\Ministries\Domain\Repository\MinistriesRepository::class);
        $repository->save($repository->find('ministries', $data['group']['id'])->archive());
        self::assertCount(0, $this->request('GET', '/api/groups')['items']);
        $this->request('GET', '/api/groups/'.$data['group']['id'], [], 403);
        $account = \App\IdentityAccess\Domain\Model\UserAccount::create(\App\IdentityAccess\Domain\ValueObject\UserId::generate(), \App\IdentityAccess\Domain\ValueObject\Username::fromString('unlinked-leader'), \App\IdentityAccess\Domain\ValueObject\PasswordHash::fromString('hash'), null);
        $storage->setToken(new \Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken(new \App\IdentityAccess\Infrastructure\Security\SymfonyUser($account), 'main', ['ROLE_USER']));
        self::assertCount(0, $this->request('GET', '/api/groups')['items']);
    }

    private function invitationFixture(): array
    {
        self::getContainer()->get(\Symfony\Component\Clock\ClockInterface::class)->modify('-1 day');
        $o = $this->occurrence();
        $volunteer = $this->volunteer($o);
        $this->request('POST', '/api/volunteering/assignments/'.$volunteer['assignment']['id'].'/status', ['status' => 'cancelled']);
        $invite = $this->request('POST', '/api/volunteer-rota/invitations', ['shiftId' => $volunteer['shift']['id'], 'personId' => $this->ids['adult'], 'expiresAt' => '2030-01-06T08:00:00Z'], 201);

        return ['o' => $o, 'volunteer' => $volunteer, 'invite' => $invite];
    }

    private function useServingAccount(): void
    {
        $this->useGroupLeader();
        self::getContainer()->get(AuthorizationCheckerInterface::class)->permissions = ['volunteering.serve'];
    }

    public function testVolunteerInvitationReservesCapacityAndScopedAcceptanceRechecksEligibility(): void
    {
        $data = $this->invitationFixture();
        self::assertSame(0, $this->request('GET', '/api/volunteer-rota')['shifts'][0]['vacancies']);
        self::assertFalse(self::getContainer()->get(\App\Volunteering\Application\ReadModel\StaffingDirectory::class)->eligibleForOccurrence($this->ids['adult'], $data['o']['id']));
        $this->request('POST', '/api/volunteering/assignments', ['shiftId' => $data['volunteer']['shift']['id'], 'personId' => $this->ids['adult']], 409);
        $this->useServingAccount();
        $schedule = $this->request('GET', '/api/volunteer-workspace');
        self::assertArrayNotHasKey('screeningValidUntil', $schedule['items'][1]);
        $url = '/api/volunteer-workspace/invitations/'.$data['invite']['id'].'/response';
        $this->request('POST', $url, ['decision' => 'accepted', 'expectedVersion' => 2], 409);
        $accepted = $this->request('POST', $url, ['decision' => 'accepted', 'expectedVersion' => 1]);
        self::assertSame('accepted', $accepted['status']);
        self::assertTrue(self::getContainer()->get(\App\Volunteering\Application\ReadModel\StaffingDirectory::class)->eligibleForOccurrence($this->ids['adult'], $data['o']['id']));
        $this->request('POST', $url, ['decision' => 'declined', 'expectedVersion' => 2], 409);
        $this->request('GET', '/api/volunteering', [], 403);
        $this->request('GET', '/api/volunteer-rota', [], 403);
        self::assertSame('assigned', self::getContainer()->get(\App\Volunteering\Domain\Repository\VolunteeringRepository::class)->find('assignments', $data['invite']['assignmentId'])->status);
    }

    public function testVolunteerInvitationExpiryWithdrawalAndEligibilityFailures(): void
    {
        $data = $this->invitationFixture();
        $id = $data['invite']['id'];
        $profile = $data['volunteer']['profile'];
        $this->request('PUT', '/api/volunteering/profiles/'.$profile['id'], ['personId' => $this->ids['adult'], 'approvedRoleIds' => [$data['volunteer']['role']['id']], 'availability' => $profile['availability'], 'screeningValidUntil' => '2029-12-31']);
        $this->useServingAccount();
        $this->request('POST', '/api/volunteer-workspace/invitations/'.$id.'/response', ['decision' => 'accepted', 'expectedVersion' => 1], 409);
        self::assertSame('pending', self::getContainer()->get(\App\Volunteering\Domain\Repository\VolunteeringRepository::class)->find('invitations', $id)->status);
        self::getContainer()->get(\Symfony\Component\Clock\ClockInterface::class)->modify('+1 day');
        $this->request('POST', '/api/volunteer-workspace/invitations/'.$id.'/response', ['decision' => 'accepted', 'expectedVersion' => 1], 409);
        $repository = self::getContainer()->get(\App\Volunteering\Domain\Repository\VolunteeringRepository::class);
        self::assertSame('expired', $repository->find('invitations', $id)->status);
        self::assertSame('cancelled', $repository->find('assignments', $data['invite']['assignmentId'])->status);
    }

    public function testServicePlansPublishedScopeResourcesOrderAndRescheduleReview(): void
    {
        $data = $this->invitationFixture();
        $payload = ['occurrenceId' => $data['o']['id'], 'title' => 'Sunday order', 'items' => [['title' => 'Welcome', 'durationMinutes' => 10, 'notes' => 'Welcome the congregation', 'resourceUrl' => 'https://example.test/order'], ['title' => 'Message', 'durationMinutes' => 30]]];
        $bad = $payload;
        $bad['items'][0]['resourceUrl'] = 'javascript:alert(1)';
        $this->request('POST', '/api/service-plans', $bad, 422);
        $bad = $payload;
        $bad['items'][0]['durationMinutes'] = 180;
        $this->request('POST', '/api/service-plans', $bad, 422);
        $plan = $this->request('POST', '/api/service-plans', $payload, 201);
        $this->request('POST', '/api/service-plans', $payload, 409);
        $this->useServingAccount();
        $this->request('GET', '/api/service-plans/'.$plan['id'], [], 403);
        self::getContainer()->get(AuthorizationCheckerInterface::class)->permissions = null;
        $this->request('PUT', '/api/service-plans/'.$plan['id'].'/publication', ['expectedVersion' => 1, 'published' => true]);
        $this->useServingAccount();
        $view = $this->request('GET', '/api/service-plans/'.$plan['id']);
        self::assertSame(40, $view['durationMinutes']);
        self::assertSame('Welcome', $view['plan']['items'][0]['title']);
        self::assertCount(1, $this->request('GET', '/api/service-plans/mine')['items']);
        $this->request('GET', '/api/service-plans', [], 403);
        $this->request('PUT', '/api/service-plans/'.$plan['id'], $payload + ['expectedVersion' => 2], 403);
        self::getContainer()->get(AuthorizationCheckerInterface::class)->permissions = null;
        $o = $data['o'];
        $this->request('PUT', '/api/events/'.$o['eventId'].'/occurrences/'.$o['id'], ['startsAt' => '2030-01-06T10:00', 'endsAt' => '2030-01-06T12:00', 'location' => 'Church', 'status' => 'scheduled'], 204);
        self::getContainer()->get(\App\Shared\Infrastructure\Outbox\OutboxProcessor::class)->drainAll();
        self::getContainer()->get(\App\Volunteering\Application\Command\SettleVolunteerInvitations\SettleVolunteerInvitationsHandler::class)(new \App\Volunteering\Application\Command\SettleVolunteerInvitations\SettleVolunteerInvitationsCommand());
        $this->request('GET', '/api/volunteer-rota');
        self::assertSame('needs_review', $this->request('GET', '/api/service-plans/'.$plan['id'])['plan']['status']);
        self::assertSame('withdrawn', self::getContainer()->get(\App\Volunteering\Domain\Repository\VolunteeringRepository::class)->find('invitations', $data['invite']['id'])->status);
        $this->useServingAccount();
        $this->request('GET', '/api/service-plans/'.$plan['id'], [], 403);
    }

    public function testVolunteerReminderDraftsAreIdempotentAndConsentStillControlsQueueing(): void
    {
        $data = $this->invitationFixture();
        $id = $data['invite']['assignmentId'];
        $url = '/api/volunteer-rota/assignments/'.$id.'/reminder';
        $first = $this->request('POST', $url, ['channel' => 'email', 'reference' => 'reminder-1'], 201);
        self::assertSame($first['id'], $this->request('POST', $url, ['channel' => 'email', 'reference' => 'reminder-1'], 201)['id']);
        $communications = $this->request('GET', '/api/communications');
        self::assertSame('draft', $communications['campaigns'][0]['status']);
        self::assertCount(0, $communications['deliveries']);
        $this->request('POST', '/api/communications/campaigns/'.$first['campaignId'].'/queue');
        self::assertSame('suppressed', $this->request('GET', '/api/communications')['deliveries'][0]['status']);
        self::getContainer()->get(AuthorizationCheckerInterface::class)->permissions = ['volunteering.manage'];
        $this->request('POST', $url, ['channel' => 'email', 'reference' => 'reminder-2'], 403);
    }

    public function testVolunteerReminderWorkerSuppressesExpiredInvitationsWithoutRotaReads(): void
    {
        $data = $this->invitationFixture();
        $this->request('POST', '/api/communications/preferences', ['personId' => $this->ids['adult'], 'channel' => 'email', 'consent' => true, 'consentNote' => 'Signed preference'], 201);
        $reminder = $this->request('POST', '/api/volunteer-rota/assignments/'.$data['invite']['assignmentId'].'/reminder', ['channel' => 'email', 'reference' => 'worker-expiry'], 201);
        $this->request('POST', '/api/communications/campaigns/'.$reminder['campaignId'].'/queue');
        self::assertSame('pending', $this->request('GET', '/api/communications')['deliveries'][0]['status']);
        self::getContainer()->get(\Symfony\Component\Clock\ClockInterface::class)->modify('+1 day');
        self::assertFalse(self::getContainer()->get(\App\Communications\Application\Command\DeliverNextMessage\DeliverNextMessageHandler::class)(new \App\Communications\Application\Command\DeliverNextMessage\DeliverNextMessageCommand()));
        $delivery = $this->request('GET', '/api/communications')['deliveries'][0];
        self::assertSame('suppressed', $delivery['status']);
        self::assertSame('Volunteer invitation expired or changed.', $delivery['error']);
        self::assertCount(0, self::getContainer()->get(\App\Communications\Application\Port\MessageGateway::class)->sent);
        self::getContainer()->get(\App\Volunteering\Application\Command\SettleVolunteerInvitations\SettleVolunteerInvitationsHandler::class)(new \App\Volunteering\Application\Command\SettleVolunteerInvitations\SettleVolunteerInvitationsCommand());
        self::assertSame(1, $this->request('GET', '/api/volunteering')['shifts'][0]['vacancies']);
    }

    public function testVolunteerResponsesRejectOtherPeopleAndDeclineFreesCapacity(): void
    {
        $data = $this->invitationFixture();
        $this->useServingAccount();
        $account = \App\IdentityAccess\Domain\Model\UserAccount::create(\App\IdentityAccess\Domain\ValueObject\UserId::generate(), \App\IdentityAccess\Domain\ValueObject\Username::fromString('other-volunteer'), \App\IdentityAccess\Domain\ValueObject\PasswordHash::fromString('hash'), $this->ids['child']);
        self::getContainer()->get(\Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface::class)->getToken()->setUser(new \App\IdentityAccess\Infrastructure\Security\SymfonyUser($account));
        self::assertCount(0, $this->request('GET', '/api/volunteer-workspace')['items']);
        $url = '/api/volunteer-workspace/invitations/'.$data['invite']['id'].'/response';
        $this->request('POST', $url, ['decision' => 'accepted', 'expectedVersion' => 1], 403);
        $this->useServingAccount();
        self::assertSame('declined', $this->request('POST', $url, ['decision' => 'declined', 'expectedVersion' => 1])['status']);
        self::getContainer()->get(AuthorizationCheckerInterface::class)->permissions = null;
        self::assertSame(1, $this->request('GET', '/api/volunteer-rota')['shifts'][0]['vacancies']);
    }

    public function testVolunteerReminderQueueSuppressesRescheduledOccurrences(): void
    {
        $data = $this->invitationFixture();
        $this->request('POST', '/api/communications/preferences', ['personId' => $this->ids['adult'], 'channel' => 'email', 'consent' => true, 'consentNote' => 'Signed preference'], 201);
        $reminder = $this->request('POST', '/api/volunteer-rota/assignments/'.$data['invite']['assignmentId'].'/reminder', ['channel' => 'email', 'reference' => 'changed-location'], 201);
        $o = $data['o'];
        $this->request('PUT', '/api/events/'.$o['eventId'].'/occurrences/'.$o['id'], ['startsAt' => '2030-01-06T09:00', 'endsAt' => '2030-01-06T12:00', 'location' => 'New hall', 'status' => 'scheduled'], 204);
        self::getContainer()->get(\App\Shared\Infrastructure\Outbox\OutboxProcessor::class)->drainAll();
        $this->request('POST', '/api/communications/campaigns/'.$reminder['campaignId'].'/queue');
        self::assertSame('suppressed', $this->request('GET', '/api/communications')['deliveries'][0]['status']);
    }

    public function testContextPermissionIsRequired(): void
    {
        self::getContainer()->set(AuthorizationCheckerInterface::class, new WorkflowTestAuthorization(false));
        $this->request('GET', '/api/volunteering', [], 403);
    }
}
final class WorkflowTestAuthorization implements AuthorizationCheckerInterface
{
    public ?array $permissions = null;

    public function __construct(public bool $allowed)
    {
    }

    public function isGranted(mixed $attribute, mixed $subject = null, ?\Symfony\Component\Security\Core\Authorization\AccessDecision $accessDecision = null): bool
    {
        return $this->allowed && (null === $this->permissions || in_array($attribute, $this->permissions, true));
    }
}
final class WorkflowsTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/onechurch-workflows-tests/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/onechurch-workflows-tests/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'workflows-tests', 'exceptions' => [\Symfony\Component\Security\Core\Exception\AccessDeniedException::class => ['status_code' => 403]], 'test' => true, 'router' => ['utf8' => true], 'serializer' => ['enabled' => true], 'validation' => ['enabled' => true], 'messenger' => ['enabled' => true]]);
        $services = $container->services()->defaults()->autowire()->autoconfigure();
        \App\Tests\Support\ApplicationPorts::reads($services);
        $services->set(Connection::class)->synthetic()->public();
        $services->set(\Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface::class, WorkflowTestTokenStorage::class)->public();
        $services->set(\App\Communications\Application\Port\MessageGateway::class)->synthetic()->public();
        $services->set(\Symfony\Component\Clock\ClockInterface::class)->synthetic()->public();
        $services->set(PersonDirectory::class)->synthetic()->public();
        $services->set(\App\People\Application\ReadModel\PeopleReportDirectory::class)->synthetic()->public();
        $services->set(\App\IdentityAccess\Infrastructure\ReadModel\DBAL\DbalIdentityAccessReadRepository::class);
        $services->alias(\App\IdentityAccess\Application\ReadModel\IdentityAccessReadRepository::class, \App\IdentityAccess\Infrastructure\ReadModel\DBAL\DbalIdentityAccessReadRepository::class);
        $services->set(AuthorizationCheckerInterface::class)->synthetic()->public();
        $services->set(\Symfony\Component\Security\Http\EventListener\IsGrantedAttributeListener::class);
        $services->set(\App\Shared\Presentation\Http\WorkflowAuditController::class);
        $services->alias('security.authorization_checker', AuthorizationCheckerInterface::class);
        $services->set('limiter.public_form_submit', \Symfony\Component\RateLimiter\RateLimiterFactory::class)->args([['id' => 'public_form_submit', 'policy' => 'fixed_window', 'limit' => 10, 'interval' => '15 minutes'], \Symfony\Component\DependencyInjection\Loader\Configurator\service(\Symfony\Component\RateLimiter\Storage\InMemoryStorage::class)]);
        $services->set(\Symfony\Component\RateLimiter\Storage\InMemoryStorage::class);
        $services->set(FlockStore::class);
        $services->set(LockFactory::class)->args([\Symfony\Component\DependencyInjection\Loader\Configurator\service(FlockStore::class)]);
        $base = dirname(__DIR__, 2).'/src';
        $services->load('App\Shared\Infrastructure\\', $base.'/Shared/Infrastructure/')->exclude([$base.'/Shared/Infrastructure/Persistence/WorkflowDocumentMapper.php', $base.'/Shared/Infrastructure/Persistence/WorkflowDocumentRepository.php']);
        foreach (['Reporting', 'ServicePlanning', 'Forms', 'Events', 'Volunteering', 'ChildrenMinistry', 'Giving', 'Attendance', 'Ministries', 'Communications'] as $context) {
            foreach (['Application', 'Infrastructure', 'Presentation'] as $section) {
                if (!is_dir($base.'/'.$context.'/'.$section)) {
                    continue;
                }
                $loader = $services->load('App\\'.$context.'\\'.$section.'\\', $base.'/'.$context.'/'.$section.'/');
                if ('Communications' === $context && 'Infrastructure' === $section) {
                    $loader->exclude([$base.'/Communications/Infrastructure/Delivery/ConfiguredMessageGateway.php']);
                }
            }
        }
        $services->set(\App\Communications\Application\Command\QueueCampaign\QueueCampaignHandler::class)->arg('$guards', \Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator('onechurch.delivery_guard'));
        $services->set(\App\Communications\Application\Command\DeliverNextMessage\DeliverNextMessageHandler::class)->arg('$guards', \Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator('onechurch.delivery_guard'));
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $base = dirname(__DIR__, 2).'/src';
        $routes->import($base.'/Shared/Presentation/Http/WorkflowAuditController.php', 'attribute');
        $routes->import($base.'/Reporting/Presentation/Http/', 'attribute');
        foreach (['Reporting', 'ServicePlanning', 'Forms', 'Events', 'Volunteering', 'ChildrenMinistry', 'Giving', 'Attendance', 'Ministries', 'Communications'] as $context) {
            if (is_dir($base.'/'.$context.'/Presentation/Http/Controller')) {
                $routes->import($base.'/'.$context.'/Presentation/Http/Controller/', 'attribute');
            }
        }
    }
}
final class WorkflowTestMessageGateway implements \App\Communications\Application\Port\MessageGateway
{
    public array $sent = [];
    public bool $configured = true;
    public bool $fails = false;
    public ?Closure $duringSend = null;

    public function configuration(): array
    {
        return ['email' => $this->configured, 'sms' => $this->configured];
    }

    public function send(string $deliveryId, string $channel, string $destination, string $subject, string $body): void
    {
        if (null !== $this->duringSend) {
            ($this->duringSend)($deliveryId);
        }
        if ($this->fails) {
            throw new RuntimeException('Secret provider credential: test-only');
        }
        $this->sent[] = compact('deliveryId', 'channel', 'destination', 'subject', 'body');
    }
}
final class WorkflowTestTokenStorage implements \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface
{
    private ?\Symfony\Component\Security\Core\Authentication\Token\TokenInterface $token = null;

    public function getToken(): ?\Symfony\Component\Security\Core\Authentication\Token\TokenInterface
    {
        return $this->token;
    }

    public function setToken(?\Symfony\Component\Security\Core\Authentication\Token\TokenInterface $token): void
    {
        $this->token = $token;
    }
}
