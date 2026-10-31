<?php

declare(strict_types=1);

namespace App\Tests\PostgreSQL;

use App\IdentityAccess\Domain\Model\UserAccount;
use App\IdentityAccess\Domain\ValueObject\PasswordHash;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\IdentityAccess\Domain\ValueObject\Username;
use App\IdentityAccess\Infrastructure\Security\SymfonyUser;
use App\Shared\Infrastructure\Audit\AuditIdentity;
use App\Shared\Infrastructure\Audit\AuditMiddleware;
use App\Shared\Infrastructure\Audit\DbalAuditHistory;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Uid\Uuid;

final class AuditingTest extends TestCase
{
    private Connection $db;
    private RequestStack $requests;
    private TokenStorage $tokens;

    protected function setUp(): void
    {
        $url = getenv('POSTGRES_TEST_URL');
        if (!$url) {
            self::markTestSkipped('Set POSTGRES_TEST_URL to a disposable migrated PostgreSQL database.');
        }
        $this->requests = new RequestStack();
        $this->tokens = new TokenStorage();
        $config = new Configuration();
        $config->setMiddlewares([new AuditMiddleware(new AuditIdentity($this->requests, $this->tokens))]);
        $params = (new DsnParser(['postgresql' => 'pdo_pgsql']))->parse($url);
        $this->db = DriverManager::getConnection($params, $config);
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            if ($this->db->isTransactionActive()) {
                $this->db->rollBack();
            }
            $this->db->close();
        }
    }

    public function testAdministratorSeedGrantsAllPermissionsWithoutDuplicatingRolesOrChangingMemberRoles(): void
    {
        $administrator = $this->db->fetchOne("SELECT id FROM roles WHERE name = 'administrator'");
        self::assertNotFalse($administrator);
        $count = (int) $this->db->fetchOne('SELECT COUNT(*) FROM permissions');
        self::assertGreaterThan(0, $count);
        self::assertSame($count, (int) $this->db->fetchOne('SELECT COUNT(*) FROM role_permissions WHERE role_id = ?', [$administrator]));
        $admin = $this->id();
        $member = $this->id();
        foreach ([[$admin, 'Admin'], [$member, 'Member portal']] as [$id, $name]) {
            $this->db->insert('roles', ['id' => $id, 'name' => $name, 'description' => 'Existing role', 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01']);
        }
        $permission = $this->id();
        $this->db->insert('permissions', ['id' => $permission, 'code' => 'test.future_permission', 'description' => 'Additional permission', 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01']);
        $this->db->insert('role_permissions', ['role_id' => $member, 'permission_id' => $permission]);
        $roles = (int) $this->db->fetchOne('SELECT COUNT(*) FROM roles');
        require_once dirname(__DIR__, 2).'/migrations-postgresql/Version20261021030000.php';
        for ($pass = 0; $pass < 2; ++$pass) {
            $migration = new \DoctrineMigrations\Version20261021030000($this->db, new \Psr\Log\NullLogger());
            $migration->up(new \Doctrine\DBAL\Schema\Schema());
            foreach ($migration->getSql() as $sql) {
                $this->db->executeStatement($sql->getStatement(), $sql->getParameters(), $sql->getTypes());
            }
        }
        self::assertSame($roles, (int) $this->db->fetchOne('SELECT COUNT(*) FROM roles'));
        foreach ([$administrator, $admin] as $id) {
            self::assertSame($count + 1, (int) $this->db->fetchOne('SELECT COUNT(*) FROM role_permissions WHERE role_id = ?', [$id]));
        }
        self::assertSame([$permission], $this->db->fetchFirstColumn('SELECT permission_id FROM role_permissions WHERE role_id = ?', [$member]));
    }

    private function id(): string
    {
        return Uuid::v7()->toRfc4122();
    }

    private function document(string $table, string $kind = 'test', array $payload = []): string
    {
        $id = $this->id();
        $this->db->insert($table, ['id' => $id, 'kind' => $kind, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'version' => 1]);

        return $id;
    }

    private function latest(string $id): array
    {
        $row = $this->db->fetchAssociative('SELECT * FROM audit_entries WHERE record_id = ? ORDER BY id DESC LIMIT 1', [$id]);
        self::assertIsArray($row);
        $row['changes'] = json_decode($row['changes'], true, flags: JSON_THROW_ON_ERROR);

        return $row;
    }

    public function testEveryDocumentContextCapturesTypedChanges(): void
    {
        foreach (['attendance' => 'attendance', 'childrenministry' => 'children', 'ministries' => 'ministries', 'volunteering' => 'volunteering', 'serviceplanning' => 'service_planning', 'giving' => 'giving', 'payments' => 'payments', 'communications' => 'communications', 'reporting' => 'reporting', 'forms' => 'forms'] as $prefix => $context) {
            $id = $this->document($prefix.'_records', payload: ['amount' => 100, 'active' => false, 'preferences' => ['sms' => false]]);
            self::assertSame($context, $this->latest($id)['context']);
            $this->db->update($prefix.'_records', ['payload' => json_encode(['amount' => 200, 'active' => true, 'preferences' => ['sms' => true, 'email' => null]]), 'version' => 2], ['id' => $id]);
            $entry = $this->latest($id);
            self::assertSame(100, $entry['changes']['/payload/amount']['from']);
            self::assertSame(200, $entry['changes']['/payload/amount']['to']);
            self::assertFalse($entry['changes']['/payload/active']['from']);
            self::assertTrue($entry['changes']['/payload/active']['to']);
            self::assertFalse($entry['changes']['/payload/preferences/email']['fromPresent']);
            self::assertTrue($entry['changes']['/payload/preferences/email']['toPresent']);
            self::assertNull($entry['changes']['/payload/preferences/email']['to']);
            self::assertSame(1, $entry['version_before']);
            self::assertSame(2, $entry['version_after']);
        }
    }

    public function testRedactionPreservesChangeEvidenceWithoutRetainingSecrets(): void
    {
        $id = $this->document('payments_records', payload: ['merchantSecret' => 'old-sensitive', 'nested' => ['accessToken' => 'old-token'], 'history' => [['providerReference' => 'secret-ref']]]);
        $this->db->update('payments_records', ['payload' => json_encode(['merchantSecret' => 'new-sensitive', 'nested' => ['accessToken' => 'new-token'], 'history' => [['providerReference' => 'new-ref']]])], ['id' => $id]);
        $entry = $this->latest($id);
        self::assertSame('[redacted]', $entry['changes']['/payload/merchantSecret']['from']);
        self::assertSame('[redacted]', $entry['changes']['/payload/merchantSecret']['to']);
        self::assertSame('[redacted]', $entry['changes']['/payload/nested/accessToken']['to']);
        self::assertStringNotContainsString('secret-ref', json_encode($entry));
        $note = $this->document('childrenministry_records', 'supportnotes', ['description' => 'Private medical detail']);
        self::assertStringNotContainsString('Private medical detail', json_encode($this->latest($note)));
        $answer = $this->document('forms_records', 'submissions', ['answers' => ['health' => 'Private answer']]);
        self::assertStringNotContainsString('Private answer', json_encode($this->latest($answer)));
    }

    public function testAttributionDoesNotLeakBetweenRequestsAndWorkers(): void
    {
        $user = UserAccount::create(UserId::generate(), Username::fromString('audit@example.test'), PasswordHash::fromString('test-hash'), null);
        $this->tokens->setToken(new UsernamePasswordToken(new SymfonyUser($user), 'main', ['ROLE_USER']));
        $request = Request::create('/api/member/profile', 'PUT');
        $request->attributes->set('_request_id', 'request-one');
        $this->requests->push($request);
        $id = $this->document('giving_records');
        $first = $this->latest($id);
        self::assertSame($user->id()->toString(), $first['actor_id']);
        self::assertSame('request-one', $first['request_id']);
        self::assertSame('member_portal', $first['source']);
        $this->requests->pop();
        $this->tokens->setToken(null);
        $this->db->update('giving_records', ['version' => 2], ['id' => $id]);
        $worker = $this->latest($id);
        self::assertNull($worker['actor_id']);
        self::assertNull($worker['request_id']);
        self::assertSame('worker', $worker['source']);
    }

    public function testDeleteRemainsReadableAndCursorPagesHaveNoDuplicates(): void
    {
        $id = $this->document('giving_records', payload: ['amount' => 100]);
        $this->db->update('giving_records', ['version' => 2], ['id' => $id]);
        $this->db->delete('giving_records', ['id' => $id]);
        self::assertSame('deleted', $this->latest($id)['operation']);
        $reader = new DbalAuditHistory($this->db);
        $first = $reader->page('giving', $id, 2);
        self::assertCount(2, $first['entries']);
        $second = $reader->page('giving', $id, 2, $first['nextBefore']);
        self::assertCount(1, $second['entries']);
        self::assertNull($second['nextBefore']);
        self::assertSame([], array_intersect(array_column($first['entries'], 'id'), array_column($second['entries'], 'id')));
    }

    public function testRollbackAndOptimisticConflictCannotProducePhantomHistory(): void
    {
        $this->db->createSavepoint('test_rollback');
        $id = $this->document('giving_records');
        $this->db->rollbackSavepoint('test_rollback');
        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM audit_entries WHERE record_id = ?', [$id]));
        $id = $this->document('giving_records');
        self::assertSame(1, $this->db->update('giving_records', ['version' => 2], ['id' => $id, 'version' => 1]));
        self::assertSame(0, $this->db->update('giving_records', ['version' => 3], ['id' => $id, 'version' => 1]));
        self::assertSame(2, (int) $this->db->fetchOne('SELECT COUNT(*) FROM audit_entries WHERE record_id = ?', [$id]));
    }

    public function testAuditFailureRollsBackTheBusinessWrite(): void
    {
        $this->db->executeStatement("CREATE FUNCTION pg_temp.reject_audit() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN RAISE EXCEPTION ''test audit failure''; END'");
        $this->db->executeStatement('CREATE TRIGGER test_reject BEFORE INSERT ON audit_entries FOR EACH ROW EXECUTE FUNCTION pg_temp.reject_audit()');
        $this->db->createSavepoint('failed_write');
        try {
            $this->document('giving_records');
            self::fail('Audit failure must abort the write.');
        } catch (\Doctrine\DBAL\Exception $error) {
            $this->db->rollbackSavepoint('failed_write');
            self::assertSame(0, (int) $this->db->fetchOne("SELECT COUNT(*) FROM giving_records WHERE kind = 'test'"));
        }
    }

    public function testHistoryCannotBeUpdatedDeletedOrTruncated(): void
    {
        $this->document('giving_records');
        foreach (["UPDATE audit_entries SET source = 'tampered'", 'DELETE FROM audit_entries', 'TRUNCATE audit_entries'] as $sql) {
            $this->db->createSavepoint('immutable');
            try {
                $this->db->executeStatement($sql);
                self::fail('History mutation must fail.');
            } catch (\Doctrine\DBAL\Exception $error) {
                $this->db->rollbackSavepoint('immutable');
                self::assertStringContainsString('append-only', $error->getMessage());
            }
        }
    }

    public function testDatabaseTriggerCoverageIncludesEveryBusinessTable(): void
    {
        $tables = $this->db->fetchFirstColumn("SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename NOT IN ('doctrine_migration_versions', 'audit_entries', 'workflow_audit', 'account_security_audit', 'integration_outbox', 'integration_deliveries', 'payment_notifications', 'lock_keys')");
        foreach ($tables as $table) {
            self::assertSame(1, (int) $this->db->fetchOne('SELECT COUNT(*) FROM pg_trigger WHERE tgrelid = CAST(? AS regclass) AND tgname = ?', [$table, 'audit_business_change']), $table.' requires auditing');
        }
    }

    public function testRelationalFieldsAndAssociationsPreserveTheirHistory(): void
    {
        $person = $this->id();
        $now = '2026-10-21 10:00:00';
        $this->db->insert('people_persons', ['id' => $person, 'first_name' => 'Before', 'last_name' => 'Member', 'created_at' => $now, 'updated_at' => $now]);
        $this->db->update('people_persons', ['first_name' => 'After'], ['id' => $person]);
        self::assertSame('Before', $this->latest($person)['changes']['/first_name']['from']);
        self::assertSame('After', $this->latest($person)['changes']['/first_name']['to']);
        $family = \App\People\Domain\Model\Family::create(\App\People\Domain\ValueObject\FamilyId::generate(), 'Family');
        $family->addMember(\App\People\Domain\ValueObject\PersonId::fromString($person), \App\People\Domain\Enum\FamilyRole::HEAD);
        $repo = new \App\People\Infrastructure\Persistence\DBAL\DbalFamilyRepository($this->db, new \App\People\Infrastructure\Persistence\DBAL\FamilyMapper());
        $repo->save($family);
        $membership = $this->db->fetchOne('SELECT id FROM people_family_memberships WHERE family_id = ?', [$family->id()->toString()]);
        $before = (int) $this->db->fetchOne('SELECT COUNT(*) FROM audit_entries WHERE table_name = ? AND record_id = ?', ['people_family_memberships', $family->id()->toString()]);
        $repo->save($repo->findById($family->id()));
        self::assertSame($membership, $this->db->fetchOne('SELECT id FROM people_family_memberships WHERE family_id = ?', [$family->id()->toString()]));
        self::assertSame($before, (int) $this->db->fetchOne('SELECT COUNT(*) FROM audit_entries WHERE table_name = ? AND record_id = ?', ['people_family_memberships', $family->id()->toString()]));
        $family->removeMember(\App\People\Domain\ValueObject\PersonId::fromString($person));
        $repo->save($family);
        self::assertSame('deleted', $this->latest($family->id()->toString())['operation']);
        self::assertSame('head', $this->latest($family->id()->toString())['changes']['/role']['from']);
    }

    public function testIndependentConnectionsShareWorkflowAdvisoryLocks(): void
    {
        $params = (new DsnParser(['postgresql' => 'pdo_pgsql']))->parse(getenv('POSTGRES_TEST_URL'));
        $other = DriverManager::getConnection($params);
        $first = (new \Symfony\Component\Lock\LockFactory(new \Symfony\Component\Lock\Store\DoctrineDbalPostgreSqlStore($this->db)))->createLock('test-shared-workflow');
        $second = (new \Symfony\Component\Lock\LockFactory(new \Symfony\Component\Lock\Store\DoctrineDbalPostgreSqlStore($other)))->createLock('test-shared-workflow');
        try {
            self::assertTrue($first->acquire());
            self::assertFalse($second->acquire());
            $first->release();
            self::assertTrue($second->acquire());
        } finally {
            $first->release();
            $second->release();
            $other->close();
        }
    }

    public function testAutocommitMetadataIsTransactionLocal(): void
    {
        $this->db->rollBack();
        // Even queries outside a repository transaction must leave no session attribution.
        $request = Request::create('/api/people', 'GET');
        $request->attributes->set('_request_id', 'autocommit-request');
        $this->requests->push($request);
        self::assertSame('autocommit-request', $this->db->fetchOne("SELECT current_setting('app.request_id', true)"));
        $native = $this->db->getNativeConnection();
        self::assertContains($native->query("SELECT current_setting('app.request_id', true)")->fetchColumn(), ['', null]);
        $this->requests->pop();
        self::assertSame('', $this->db->fetchOne("SELECT current_setting('app.request_id', true)"));
    }

    public function testRuntimeRoleCanWriteBusinessDataButCannotForgeHistory(): void
    {
        $role = 'audit_test_'.bin2hex(random_bytes(6));
        $this->db->executeStatement('CREATE ROLE '.$role.' NOLOGIN');
        $command = new \Symfony\Component\Console\Tester\CommandTester(new \App\Shared\Infrastructure\PostgreSQL\GrantRuntimeCommand($this->db));
        self::assertSame(0, $command->execute(['role' => $role]));
        $this->db->executeStatement('SET ROLE '.$role);
        try {
            $id = $this->document('giving_records');
            self::assertSame('created', $this->latest($id)['operation']);
            foreach (['INSERT INTO audit_entries DEFAULT VALUES', 'DELETE FROM audit_entries', 'UPDATE audit_entries SET source = source', 'TRUNCATE giving_records'] as $sql) {
                $this->db->createSavepoint('runtime_denied');
                try {
                    $this->db->executeStatement($sql);
                    self::fail('Runtime role must not bypass audit protections.');
                } catch (\Doctrine\DBAL\Exception $error) {
                    $this->db->rollbackSavepoint('runtime_denied');
                    self::assertStringContainsString('permission denied', $error->getMessage());
                }
            }
        } finally {
            $this->db->executeStatement('RESET ROLE');
        }
    }

    public function testConcurrentWritersCannotAuditTheSameExpectedVersion(): void
    {
        $params = (new DsnParser(['postgresql' => 'pdo_pgsql']))->parse(getenv('POSTGRES_TEST_URL'));
        $writer = DriverManager::getConnection($params);
        $monitor = DriverManager::getConnection($params);
        $id = $this->id();
        $application = 'audit_conflict_'.bin2hex(random_bytes(6));
        $writer->insert('giving_records', ['id' => $id, 'kind' => 'concurrency_fixture', 'payload' => '{}', 'version' => 1]);
        $writer->beginTransaction();
        $writer->update('giving_records', ['version' => 2], ['id' => $id, 'version' => 1]);
        $code = 'require '.var_export(dirname(__DIR__, 2).'/vendor/autoload.php', true).';$p = (new Doctrine\DBAL\Tools\DsnParser(["postgresql" => "pdo_pgsql"]))->parse(getenv("POSTGRES_TEST_URL"));$db = Doctrine\DBAL\DriverManager::getConnection($p);$db->executeStatement("SET lock_timeout = \'5s\'");$db->executeStatement("SELECT set_config(\'application_name\', ?, false)", ['.var_export($application, true).']);echo "ready\n"; flush();echo $db->update("giving_records", ["version" => 3], ["id" => '.var_export($id, true).', "version" => 1]);';
        $process = proc_open([PHP_BINARY, '-r', $code], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        try {
            self::assertSame("ready\n", fgets($pipes[1]));
            $blocked = false;
            for ($attempt = 0; $attempt < 80; ++$attempt) {
                $blocked = 1 === (int) $monitor->fetchOne("SELECT COUNT(*) FROM pg_stat_activity WHERE application_name = ? AND wait_event_type = 'Lock'", [$application]);
                if ($blocked) {
                    break;
                }
                usleep(25000);
            }
            $writer->commit();
            self::assertTrue($blocked, 'Second process must contend on the first writer row lock.');
            self::assertSame('0', stream_get_contents($pipes[1]));
            self::assertSame('', stream_get_contents($pipes[2]));
            self::assertSame(2, (int) $monitor->fetchOne('SELECT COUNT(*) FROM audit_entries WHERE record_id = ?', [$id]));
        } finally {
            if ($writer->isTransactionActive()) {
                $writer->rollBack();
            }
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($process);
            $writer->delete('giving_records', ['id' => $id]);
            $writer->close();
            $monitor->close();
        }
    }

    public function testIdentityWritesRedactPasswordsAndKeepRoleAssociationsStable(): void
    {
        $user = UserAccount::create(UserId::generate(), Username::fromString('history-'.bin2hex(random_bytes(4)).'@example.test'), PasswordHash::fromString('first-hash'), null);
        $users = new \App\IdentityAccess\Infrastructure\Persistence\DBAL\DbalUserAccountRepository($this->db, new \App\IdentityAccess\Infrastructure\Persistence\DBAL\UserAccountMapper());
        $users->save($user);
        $user->changePassword(PasswordHash::fromString('second-hash'));
        $users->save($user);
        $entry = $this->latest($user->id()->toString());
        self::assertSame('[redacted]', $entry['changes']['/password_hash']['from']);
        self::assertSame('[redacted]', $entry['changes']['/password_hash']['to']);
        self::assertStringNotContainsString('second-hash', json_encode($entry));
        $role = \App\IdentityAccess\Domain\Model\Role::create(\App\IdentityAccess\Domain\ValueObject\RoleId::generate(), 'Audit fixture', 'Test role');
        (new \App\IdentityAccess\Infrastructure\Persistence\DBAL\DbalRoleRepository($this->db, new \App\IdentityAccess\Infrastructure\Persistence\DBAL\RoleMapper()))->save($role);
        $user->assignRole($role->id());
        $users->save($user);
        $users->save($user);
        self::assertSame(1, (int) $this->db->fetchOne("SELECT COUNT(*) FROM audit_entries WHERE table_name = 'user_roles' AND record_id = ?", [$user->id()->toString()]));
        $user->removeRole($role->id());
        $users->save($user);
        $entry = $this->latest($user->id()->toString());
        self::assertSame('deleted', $entry['operation']);
        self::assertSame($role->id()->toString(), $entry['changes']['/role_id']['from']);
    }
}
