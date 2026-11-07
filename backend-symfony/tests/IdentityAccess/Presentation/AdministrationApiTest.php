<?php

declare(strict_types=1);

namespace App\Tests\IdentityAccess\Presentation;

use App\Tests\Support\IdentityApiTestCase;

final class AdministrationApiTest extends IdentityApiTestCase
{
    private string $adminId;
    private string $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminId = $this->account('admin', administrator: true);
        $this->admin = $this->login('admin');
    }

    public function testAdministratorCreatesAndReadsAnAccount(): void
    {
        $this->call('POST', '/api/users', $this->admin, [
            'username' => 'Ada.Lovelace', 'email' => 'Ada@Example.test', 'displayName' => 'Ada Lovelace', 'password' => 'analytical engine',
        ]);
        self::assertSame(201, $this->code());
        $id = (string) $this->json()['id'];

        $this->call('GET', "/api/users/$id", $this->admin);
        self::assertSame(200, $this->code());
        self::assertSame('ada.lovelace', $this->json()['username']);
        self::assertSame('ada@example.test', $this->json()['email']);
        self::assertSame([], $this->json()['roles']);

        self::assertNotSame('', $this->login('ada.lovelace', 'analytical engine'));
        self::assertSame(1, $this->rows('identity_access_audit', "action = 'account.created' AND target_id = '$id' AND actor_id = '{$this->adminId}'"));
        self::assertStringNotContainsString('analytical engine', (string) $this->connection->fetchOne('SELECT password_hash FROM user_accounts WHERE id = ?', [$id]));
    }

    public function testDuplicateUsernameOrEmailIsAConflictAndRollsBackTheAudit(): void
    {
        $body = ['username' => 'ada', 'email' => 'ada@example.test', 'displayName' => 'Ada', 'password' => 'analytical engine'];
        $this->call('POST', '/api/users', $this->admin, $body);
        self::assertSame(201, $this->code());
        $audits = $this->rows('identity_access_audit');

        $this->call('POST', '/api/users', $this->admin, $body);
        self::assertSame(409, $this->code());
        self::assertSame('A duplicate or overlapping record already exists.', $this->message());

        $this->call('POST', '/api/users', $this->admin, ['username' => 'other', 'email' => 'ada@example.test'] + $body);
        self::assertSame(409, $this->code());
        self::assertSame($audits, $this->rows('identity_access_audit'));
    }

    public function testInvalidAccountDetailsAreRejected(): void
    {
        $valid = ['username' => 'ada', 'email' => 'ada@example.test', 'displayName' => 'Ada', 'password' => 'analytical engine'];
        foreach (['username' => 'a!', 'email' => 'not-an-email', 'displayName' => '', 'password' => 'short'] as $field => $bad) {
            $this->call('POST', '/api/users', $this->admin, [$field => $bad] + $valid);
            self::assertSame(400, $this->code(), $field);
        }
        self::assertSame(1, $this->rows('user_accounts'));
    }

    public function testManagementRequiresTheIdentityManagePermission(): void
    {
        $this->account('member');
        $member = $this->login('member');
        $id = $this->adminId;

        foreach ([['GET', '/api/users'], ['GET', "/api/users/$id"], ['GET', '/api/roles'], ['GET', '/api/permissions'], ['POST', "/api/users/$id/disable"]] as [$method, $uri]) {
            $this->call($method, $uri, $member);
            self::assertSame(403, $this->code(), "$method $uri");
            self::assertSame('Permission denied.', $this->message());
        }
    }

    public function testMembersReadTheirOwnAccountThroughMeNotTheAdminRoutes(): void
    {
        $id = $this->account('member');
        $member = $this->login('member');

        $this->call('GET', '/api/me', $member);
        self::assertSame(200, $this->code());
        self::assertSame($id, $this->json()['id']);

        $this->call('GET', "/api/users/$id", $member);
        self::assertSame(403, $this->code());
    }

    public function testSearchMatchesUsernameEmailAndNameAndEscapesWildcards(): void
    {
        $this->account('alice');
        $this->account('bob');
        $this->account('b_b');

        $this->call('GET', '/api/users?search=ALI', $this->admin);
        self::assertSame(['alice'], array_column($this->json()['items'], 'username'));

        $this->call('GET', '/api/users?search=b_b', $this->admin);
        self::assertSame(['b_b'], array_column($this->json()['items'], 'username'), 'underscore is literal, not a wildcard');

        $this->call('GET', '/api/users?limit=2', $this->admin);
        self::assertCount(2, $this->json()['items']);

        $this->call('GET', '/api/users?limit=101', $this->admin);
        self::assertSame(400, $this->code());
    }

    public function testAccountVersionsAreExposedAndCheckedWhenSupplied(): void
    {
        $id = $this->account('ada');
        $this->call('GET', "/api/users/$id", $this->admin);
        self::assertSame(0, $this->json()['version']);

        $this->call('PUT', "/api/users/$id", $this->admin, ['email' => 'ada@new.test', 'displayName' => 'Ada', 'expectedVersion' => 0]);
        self::assertSame(204, $this->code());
        $this->call('PUT', "/api/users/$id", $this->admin, ['email' => 'ada@stale.test', 'displayName' => 'Ada', 'expectedVersion' => 0]);
        self::assertSame(409, $this->code());
        self::assertSame('The record has changed. Reload and try again.', $this->message());
        self::assertSame('ada@new.test', $this->connection->fetchOne('SELECT email FROM user_accounts WHERE id = ?', [$id]));

        // Older clients send no version: the change applies and still advances it.
        $this->call('PUT', "/api/users/$id", $this->admin, ['email' => 'ada@later.test', 'displayName' => 'Ada']);
        self::assertSame(204, $this->code());
        $this->call('GET', "/api/users/$id", $this->admin);
        self::assertSame(2, $this->json()['version']);

        $this->call('PUT', "/api/users/$id", $this->admin, ['email' => 'x@y.test', 'displayName' => 'Ada', 'expectedVersion' => -1]);
        self::assertSame(400, $this->code());
    }

    public function testMalformedIdentifiersAreBadRequests(): void
    {
        $this->call('GET', '/api/users/not-a-uuid', $this->admin);

        self::assertSame(400, $this->code());
    }

    public function testUnknownAccountIsNotFound(): void
    {
        $this->call('GET', '/api/users/99999999-9999-4999-8999-999999999999', $this->admin);

        self::assertSame(404, $this->code());
    }

    public function testProfileUpdateKeepsUsernameAndAudits(): void
    {
        $id = $this->account('ada');

        $this->call('PUT', "/api/users/$id", $this->admin, ['email' => 'new@example.test', 'displayName' => 'Countess']);

        self::assertSame(204, $this->code());
        $row = $this->connection->fetchAssociative('SELECT * FROM user_accounts WHERE id = ?', [$id]);
        self::assertIsArray($row);
        self::assertSame('ada', $row['username']);
        self::assertSame('new@example.test', $row['email']);
        self::assertSame(1, $this->rows('identity_access_audit', "action = 'account.updated'"));
    }

    public function testAdministratorPasswordResetRevokesTheTargetsSessions(): void
    {
        $this->account('ada');
        $session = $this->login('ada');
        $id = (string) $this->connection->fetchOne("SELECT id FROM user_accounts WHERE username = 'ada'");

        $this->call('PUT', "/api/users/$id/password", $this->admin, ['password' => 'a reset password!']);

        self::assertSame(204, $this->code());
        $this->call('GET', '/api/me', $session);
        self::assertSame(401, $this->code());
        $this->call('GET', '/api/me', $this->admin);
        self::assertSame(200, $this->code(), 'the administrator keeps their own session');
        self::assertNotSame('', $this->login('ada', 'a reset password!'));
    }

    public function testDisablingRevokesSessionsAndEnablingDoesNotReviveThem(): void
    {
        $id = $this->account('ada');
        $session = $this->login('ada');

        $this->call('POST', "/api/users/$id/disable", $this->admin);
        self::assertSame(204, $this->code());
        $this->call('GET', '/api/me', $session);
        self::assertSame(401, $this->code());

        $this->call('POST', "/api/users/$id/enable", $this->admin);
        self::assertSame(204, $this->code());
        $this->call('GET', '/api/me', $session);
        self::assertSame(401, $this->code(), 'revoked sessions stay revoked');
        self::assertNotSame('', $this->login('ada'));
    }

    public function testTheLastActiveAccountManagerCannotBeDisabledOrDemoted(): void
    {
        $this->call('POST', "/api/users/{$this->adminId}/disable", $this->admin);
        self::assertSame(409, $this->code());
        self::assertSame('At least one active account manager must remain.', $this->message());
        self::assertSame('active', $this->connection->fetchOne('SELECT status FROM user_accounts WHERE id = ?', [$this->adminId]), 'the status change rolled back');
        self::assertSame(1, $this->rows('account_sessions'), 'sessions were not revoked');

        $this->call('DELETE', '/api/users/'.$this->adminId.'/roles/'.self::ADMIN_ROLE, $this->admin);
        self::assertSame(409, $this->code());
        self::assertSame(1, $this->rows('user_roles', "user_id = '{$this->adminId}'"));

        $this->call('DELETE', '/api/roles/'.self::ADMIN_ROLE.'/permissions/00000000-0000-0000-0000-000000000002', $this->admin);
        self::assertSame(409, $this->code());
        self::assertSame(1, $this->rows('role_permissions', "role_id = '".self::ADMIN_ROLE."' AND permission_id = '00000000-0000-0000-0000-000000000002'"));
    }

    public function testAManagerMayLeaveOnceAnotherActiveManagerExists(): void
    {
        $other = $this->account('second', administrator: true);

        $this->call('POST', "/api/users/{$this->adminId}/disable", $this->admin);
        self::assertSame(204, $this->code());

        $second = $this->login('second');
        $this->call('POST', "/api/users/$other/disable", $second);
        self::assertSame(409, $this->code());
    }

    public function testRoleAndPermissionCatalogue(): void
    {
        $this->call('POST', '/api/roles', $this->admin, ['name' => 'Treasurer', 'description' => ' Handles giving ']);
        self::assertSame(201, $this->code());
        $role = (string) $this->json()['id'];
        $this->call('POST', '/api/permissions', $this->admin, ['code' => 'Reports.View']);
        self::assertSame(201, $this->code());
        $permission = (string) $this->json()['id'];

        $this->call('GET', '/api/roles', $this->admin);
        self::assertContains('treasurer', array_column($this->json(), 'name'));
        $this->call('GET', '/api/permissions', $this->admin);
        self::assertContains('reports.view', array_column($this->json(), 'code'));

        $account = $this->account('ada');
        $this->call('POST', "/api/roles/$role/permissions/$permission", $this->admin);
        self::assertSame(204, $this->code());
        $this->call('POST', "/api/roles/$role/permissions/$permission", $this->admin);
        self::assertSame(204, $this->code(), 'repeated grants are idempotent');
        $this->call('POST', "/api/users/$account/roles/$role", $this->admin);
        self::assertSame(204, $this->code());

        $this->call('GET', "/api/users/$account/permissions", $this->admin);
        self::assertSame(['reports.view'], $this->json());
        $this->call('GET', "/api/users/$account", $this->admin);
        self::assertSame(['treasurer'], $this->json()['roles']);
    }

    public function testPermissionChangesApplyToExistingSessionsImmediately(): void
    {
        $this->account('ada');
        $session = $this->login('ada');
        $this->call('GET', '/api/roles', $session);
        self::assertSame(403, $this->code());

        $ada = (string) $this->connection->fetchOne("SELECT id FROM user_accounts WHERE username = 'ada'");
        $this->call('POST', "/api/users/$ada/roles/".self::ADMIN_ROLE, $this->admin);
        $this->call('GET', '/api/roles', $session);

        self::assertSame(200, $this->code(), 'permissions are loaded live, not frozen in the token');
    }

    public function testInvalidRoleAndPermissionNamesAreRejected(): void
    {
        foreach ([['/api/roles', ['name' => '-bad']], ['/api/roles', ['name' => 'a b']], ['/api/permissions', ['code' => 'nodot']], ['/api/permissions', ['code' => 'a.b.c']]] as [$uri, $body]) {
            $this->call('POST', $uri, $this->admin, $body);
            self::assertSame(400, $this->code(), $uri.json_encode($body));
        }
    }

    public function testUnknownRoleOrPermissionIsNotFound(): void
    {
        $this->call('POST', '/api/users/'.$this->adminId.'/roles/99999999-9999-4999-8999-999999999999', $this->admin);
        self::assertSame(404, $this->code());
        $this->call('POST', '/api/roles/'.self::ADMIN_ROLE.'/permissions/99999999-9999-4999-8999-999999999999', $this->admin);
        self::assertSame(404, $this->code());
    }
}
