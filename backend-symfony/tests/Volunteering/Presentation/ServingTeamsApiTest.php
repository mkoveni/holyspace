<?php

declare(strict_types=1);

namespace App\Tests\Volunteering\Presentation;

use App\Tests\Support\IdentityApiTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Teams, roles, memberships, role approvals and revocation (Java `ServingTeamsController`, `ServingRolesController` and `TeamManagement`). Java has
 * one test here (`protectedEndpointsRejectAnonymousAndReadOnlyCoordinators`); the rest follow the Java source rules.
 * Volunteer profiles and credential types are seeded in SQL until their slice (V3) is ported.
 */
final class ServingTeamsApiTest extends IdentityApiTestCase
{
    private const string ROOT = '/api/volunteering/teams';

    private string $admin;

    private string $adminId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminId = $this->account('admin', administrator: true);
        $this->admin = $this->login('admin');
    }

    private function team(string $name = 'Welcome team'): string
    {
        $this->call('POST', self::ROOT, $this->admin, ['name' => $name]);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    private function credentialType(string $code): string
    {
        $id = Uuid::v4()->toRfc4122();
        $this->connection->insert('serving_credential_types', ['id' => $id, 'code' => $code, 'name' => ucfirst($code)]);

        return $id;
    }

    private function volunteer(string $firstName = 'Solly'): string
    {
        $this->call('POST', '/api/people', $this->admin, ['person' => ['firstName' => $firstName, 'lastName' => 'Mabasa']]);
        self::assertSame(201, $this->code(), $this->message());
        $id = Uuid::v4()->toRfc4122();
        $this->connection->executeStatement(
            "INSERT INTO serving_volunteers (id, person_id, status, created_at, updated_at) VALUES (?, ?, 'ACTIVE', now(), now())",
            [$id, (string) $this->json()['id']],
        );

        return $id;
    }

    /** @param list<string> $requirements */
    private function role(string $team, int $version, string $name = 'Welcomer', array $requirements = [], string $childrenRole = 'NONE'): void
    {
        $this->call('POST', self::ROOT."/$team/roles", $this->admin, ['expectedVersion' => $version, 'name' => $name, 'childrenRole' => $childrenRole, 'requiredCredentials' => $requirements]);
    }

    private function join(string $team, int $version, string $volunteer, string $from = '2026-03-01T08:00:00Z', string $until = '2026-06-01T08:00:00Z'): void
    {
        $this->call('POST', self::ROOT."/$team/memberships", $this->admin, ['expectedVersion' => $version, 'volunteerId' => $volunteer, 'validFrom' => $from, 'validUntil' => $until]);
    }

    /** @return array<string|int, mixed> */
    private function details(string $team): array
    {
        $this->call('GET', self::ROOT."/$team", $this->admin);
        self::assertSame(200, $this->code(), $this->message());

        return $this->json();
    }

    private function audits(string $action): int
    {
        return $this->rows('serving_audit', "action = '$action'");
    }

    public function testProtectedEndpointsRejectAnonymousAndReadOnlyCoordinators(): void
    {
        $this->accountWith('reader', ['volunteering.read']);
        $reader = $this->login('reader');

        $this->call('GET', self::ROOT);
        self::assertSame(401, $this->code());
        $this->call('POST', self::ROOT, $reader, ['name' => 'Forbidden']);
        self::assertSame(403, $this->code());
        $this->call('GET', self::ROOT, $reader);
        self::assertSame(200, $this->code());

        $this->accountWith('nobody', []);
        $this->call('GET', self::ROOT, $this->login('nobody'));
        self::assertSame(403, $this->code());
        self::assertSame(0, $this->rows('serving_teams'));
    }

    public function testTeamsAreCreatedListedAndChangedWithVersions(): void
    {
        $team = $this->team('  Welcome team  ');
        $this->team('Audio');

        $this->call('GET', self::ROOT.'?limit=1', $this->admin);
        self::assertSame(['teams' => [['id' => $this->json()['teams'][0]['id'], 'name' => 'Audio', 'active' => true, 'version' => 0]], 'total' => 2], $this->json());
        self::assertSame(['id' => $team, 'name' => 'Welcome team', 'active' => true, 'version' => 0], $this->details($team)['team']);

        $this->call('PUT', self::ROOT."/$team", $this->admin, ['expectedVersion' => 0, 'name' => 'Welcome', 'active' => false]);
        self::assertSame(204, $this->code(), (string) $this->client->getResponse()->getContent());
        self::assertSame(['id' => $team, 'name' => 'Welcome', 'active' => false, 'version' => 1], $this->details($team)['team']);

        $this->call('PUT', self::ROOT."/$team", $this->admin, ['expectedVersion' => 0, 'name' => 'Stale', 'active' => true]);
        self::assertSame(409, $this->code());
        self::assertSame('This record changed. Refresh before saving.', $this->message());
        self::assertSame(2, $this->audits('team.created'));
        self::assertSame(1, $this->audits('team.changed'));
        self::assertSame($this->adminId, $this->connection->fetchOne("SELECT actor_id FROM serving_audit WHERE action = 'team.changed'"));

        $this->call('PUT', self::ROOT.'/'.Uuid::v4()->toRfc4122(), $this->admin, ['expectedVersion' => 0, 'name' => 'Missing', 'active' => true]);
        self::assertSame(404, $this->code());
        self::assertSame('Team was not found.', $this->message());
    }

    public function testRolesNeedCatalogueRequirementsAndKeepTheirChildrenResponsibility(): void
    {
        $team = $this->team();
        $screening = $this->credentialType('screening');
        $training = $this->credentialType('training');

        $this->role($team, 0, requirements: [Uuid::v4()->toRfc4122()]);
        self::assertSame(404, $this->code());
        self::assertSame('Credential type was not found.', $this->message());
        $this->role($team, 0, childrenRole: 'HELPER');
        self::assertSame(400, $this->code());
        self::assertSame('Choose a children responsibility or none.', $this->message());
        $this->role($team, 0, requirements: [$screening, $screening]);
        self::assertSame(400, $this->code());
        self::assertSame('Choose at most 30 unique credential requirements.', $this->message());
        self::assertSame(0, $this->rows('serving_roles'), 'failed role commands write nothing');
        self::assertSame(0, (int) $this->details($team)['team']['version']);

        $this->role($team, 0, 'Leader', [strtoupper($screening)], 'LEADER');
        self::assertSame(201, $this->code(), $this->message());
        $role = (string) $this->json()['id'];
        $this->role($team, 0, 'Second');
        self::assertSame(409, $this->code(), 'the team version moved');
        $this->role($team, 1, 'Leader');
        self::assertSame(409, $this->code());
        self::assertSame('This volunteer, account, name or request is already in use. Refresh before trying again.', $this->message());

        $details = $this->details($team);
        self::assertSame(1, $details['team']['version']);
        self::assertSame([['id' => $role, 'teamId' => $team, 'name' => 'Leader', 'childrenRole' => 'LEADER', 'active' => true, 'requiredCredentials' => [$screening]]], $details['roles']);

        $edit = static fn (int $version, string $childrenRole, array $requirements): array => ['expectedVersion' => $version, 'name' => 'Lead', 'childrenRole' => $childrenRole, 'active' => false, 'requiredCredentials' => $requirements];
        $this->call('PUT', self::ROOT."/$team/roles/$role", $this->admin, $edit(1, 'ASSISTANT', [$screening]));
        self::assertSame(409, $this->code());
        self::assertSame('Create a separate role for a different children responsibility. Existing approvals must retain their scope.', $this->message());
        $this->call('PUT', self::ROOT."/$team/roles/$role", $this->admin, $edit(1, 'LEADER', [Uuid::v4()->toRfc4122()]));
        self::assertSame(400, $this->code());
        self::assertSame('Choose known credential types.', $this->message());
        $this->call('PUT', self::ROOT.'/'.$this->team('Other')."/roles/$role", $this->admin, $edit(0, 'LEADER', []));
        self::assertSame(404, $this->code());
        self::assertSame('Team role was not found.', $this->message());

        $this->call('PUT', self::ROOT."/$team/roles/$role", $this->admin, $edit(1, 'LEADER', [$training]));
        self::assertSame(204, $this->code(), (string) $this->client->getResponse()->getContent());
        $details = $this->details($team);
        self::assertSame(2, $details['team']['version']);
        self::assertSame(['id' => $role, 'teamId' => $team, 'name' => 'Lead', 'childrenRole' => 'LEADER', 'active' => false, 'requiredCredentials' => [$training]], $details['roles'][0]);
        self::assertSame(1, $this->audits('team.role.created'));
        self::assertSame(1, $this->audits('team.role.changed'));
    }

    public function testMembershipsCannotOverlapAndNeedAnActiveTeam(): void
    {
        $team = $this->team();
        $volunteer = $this->volunteer();

        $this->join($team, 0, $volunteer, '2026-03-01T08:00:00.123456Z');
        self::assertSame(201, $this->code(), $this->message());
        $membership = (string) $this->json()['id'];
        $this->join($team, 1, $volunteer, '2026-05-01T08:00:00Z', '2026-07-01T08:00:00Z');
        self::assertSame(409, $this->code());
        self::assertSame('A team membership already covers part of this interval.', $this->message());
        $this->join($team, 1, $volunteer, '2026-06-01T08:00:00Z', '2026-07-01T08:00:00Z');
        self::assertSame(201, $this->code(), 'an adjacent period does not overlap');

        $details = $this->details($team);
        self::assertSame(2, $details['team']['version']);
        self::assertSame(['id' => $membership, 'teamId' => $team, 'volunteerId' => $volunteer, 'validFrom' => '2026-03-01T08:00:00.123456Z', 'validUntil' => '2026-06-01T08:00:00Z', 'revokedAt' => null], $details['memberships'][1]);
        self::assertSame([], $details['approvals']);
        self::assertSame(2, $this->audits('team.member.added'));

        $this->join($team, 2, Uuid::v4()->toRfc4122());
        self::assertSame(404, $this->code());
        self::assertSame('Volunteer was not found.', $this->message());
        $this->join($team, 2, $volunteer, '2026-09-01T08:00:00Z', '2026-09-01T08:00:00Z');
        self::assertSame(400, $this->code());
        self::assertSame('The end must follow the start.', $this->message());

        $this->call('PUT', self::ROOT."/$team", $this->admin, ['expectedVersion' => 2, 'name' => 'Welcome team', 'active' => false]);
        self::assertSame(204, $this->code());
        $this->join($team, 3, $volunteer, '2026-09-01T08:00:00Z', '2026-10-01T08:00:00Z');
        self::assertSame(409, $this->code());
        self::assertSame('The team is inactive.', $this->message());
        self::assertSame(2, $this->rows('serving_memberships'));
    }

    private function roleId(string $team, int $version, string $name = 'Welcomer'): string
    {
        $this->role($team, $version, $name);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    /** @param array<string, mixed> $body */
    private function approve(string $team, array $body): void
    {
        $this->call('POST', self::ROOT."/$team/approvals", $this->admin, $body + ['validFrom' => '2026-03-01T08:00:00Z', 'validUntil' => '2026-06-01T08:00:00Z']);
    }

    /** @param array<string, mixed> $body */
    private function revoke(string $team, array $body): void
    {
        $this->call('POST', self::ROOT."/$team/revoke", $this->admin, $body);
    }

    public function testRoleApprovalsNeedARoleOfTheTeamAndCannotOverlap(): void
    {
        $team = $this->team();
        $other = $this->team('Audio');
        $volunteer = $this->volunteer();
        $role = $this->roleId($team, 0);
        $foreign = $this->roleId($other, 0, 'Sound');

        $this->approve($team, ['expectedVersion' => 1, 'volunteerId' => $volunteer, 'roleId' => $role, 'validFrom' => '2026-03-01T08:00:00.5Z']);
        self::assertSame(201, $this->code(), $this->message());
        $approval = (string) $this->json()['id'];
        $this->approve($team, ['expectedVersion' => 2, 'volunteerId' => $volunteer, 'roleId' => $role, 'validFrom' => '2026-05-01T08:00:00Z', 'validUntil' => '2026-07-01T08:00:00Z']);
        self::assertSame(409, $this->code());
        self::assertSame('A role approval already covers part of this interval.', $this->message());
        $this->approve($team, ['expectedVersion' => 2, 'volunteerId' => $volunteer, 'roleId' => $role, 'validFrom' => '2026-06-01T08:00:00Z', 'validUntil' => '2026-07-01T08:00:00Z']);
        self::assertSame(201, $this->code(), 'an adjacent period does not overlap');

        $this->approve($team, ['expectedVersion' => 3, 'volunteerId' => $volunteer, 'roleId' => $foreign]);
        self::assertSame(404, $this->code());
        self::assertSame('Team role was not found.', $this->message());
        $this->approve($team, ['expectedVersion' => 3, 'volunteerId' => Uuid::v4()->toRfc4122(), 'roleId' => $role]);
        self::assertSame(404, $this->code());
        self::assertSame('Volunteer was not found.', $this->message());
        $this->approve($team, ['expectedVersion' => 2, 'volunteerId' => $volunteer, 'roleId' => $role, 'validFrom' => '2026-09-01T08:00:00Z', 'validUntil' => '2026-10-01T08:00:00Z']);
        self::assertSame(409, $this->code());
        self::assertSame('This record changed. Refresh before saving.', $this->message());
        $this->approve($team, ['expectedVersion' => 3, 'volunteerId' => $volunteer, 'roleId' => $role, 'validFrom' => '2026-09-01T08:00:00Z', 'validUntil' => '2026-09-01T08:00:00Z']);
        self::assertSame(400, $this->code());
        self::assertSame('The end must follow the start.', $this->message());

        $details = $this->details($team);
        self::assertSame(3, $details['team']['version']);
        self::assertCount(2, $details['approvals']);
        self::assertSame(['id' => $approval, 'roleId' => $role, 'volunteerId' => $volunteer, 'validFrom' => '2026-03-01T08:00:00.5Z', 'validUntil' => '2026-06-01T08:00:00Z', 'revokedAt' => null], $details['approvals'][1]);
        self::assertSame(2, $this->audits('team.role.approved'));
        self::assertSame(2, $this->rows('serving_role_approvals'));
        self::assertSame(1, $this->details($other)['team']['version'], 'the other team is untouched');
    }

    public function testRevokingEndsAnApprovalOrAMembershipOnThatTeamOnly(): void
    {
        $team = $this->team();
        $other = $this->team('Audio');
        $volunteer = $this->volunteer();
        $role = $this->roleId($team, 0);
        $this->approve($team, ['expectedVersion' => 1, 'volunteerId' => $volunteer, 'roleId' => $role]);
        $approval = (string) $this->json()['id'];
        $this->join($team, 2, $volunteer);
        $membership = (string) $this->json()['id'];
        $this->join($other, 0, $volunteer);
        $foreign = (string) $this->json()['id'];

        foreach ([[$membership, true, 'Role approval'], [$approval, false, 'Membership'], [$foreign, false, 'Membership']] as [$record, $roleApproval, $name]) {
            $this->revoke($team, ['expectedVersion' => 3, 'volunteerId' => $volunteer, 'recordId' => $record, 'roleApproval' => $roleApproval]);
            self::assertSame(404, $this->code());
            self::assertSame($name.' was not found.', $this->message());
        }
        $this->revoke($team, ['expectedVersion' => 2, 'volunteerId' => $volunteer, 'recordId' => $approval, 'roleApproval' => true]);
        self::assertSame(409, $this->code());

        $this->revoke($team, ['expectedVersion' => 3, 'volunteerId' => $volunteer, 'recordId' => $approval, 'roleApproval' => true]);
        self::assertSame(204, $this->code(), (string) $this->client->getResponse()->getContent());
        $this->revoke($team, ['expectedVersion' => 4, 'volunteerId' => $volunteer, 'recordId' => $membership]);
        self::assertSame(204, $this->code(), 'a missing roleApproval revokes a membership');
        $first = $this->connection->fetchOne('SELECT revoked_at FROM serving_role_approvals WHERE id = ?', [$approval]);
        $this->revoke($team, ['expectedVersion' => 5, 'volunteerId' => $volunteer, 'recordId' => $approval, 'roleApproval' => true]);
        self::assertSame(204, $this->code());

        $details = $this->details($team);
        self::assertSame(6, $details['team']['version']);
        self::assertNotNull($details['approvals'][0]['revokedAt']);
        self::assertNotNull($details['memberships'][0]['revokedAt']);
        self::assertSame($first, $this->connection->fetchOne('SELECT revoked_at FROM serving_role_approvals WHERE id = ?', [$approval]), 'the first revocation instant is kept');
        self::assertNull($this->details($other)['memberships'][0]['revokedAt']);
        self::assertSame(2, $this->audits('team.approval.revoked'));
        self::assertSame(1, $this->audits('team.membership.revoked'));

        $this->approve($team, ['expectedVersion' => 6, 'volunteerId' => $volunteer, 'roleId' => $role]);
        self::assertSame(201, $this->code(), 'a revoked approval no longer blocks its period');
        $this->join($team, 7, $volunteer);
        self::assertSame(201, $this->code(), 'a revoked membership no longer blocks its period');
    }

    public function testApprovalAndRevocationRefuseReadOnlyCoordinators(): void
    {
        $team = $this->team();
        $this->accountWith('reader', ['volunteering.read']);
        $reader = $this->login('reader');
        $body = ['expectedVersion' => 0, 'volunteerId' => Uuid::v4()->toRfc4122(), 'roleId' => Uuid::v4()->toRfc4122(), 'recordId' => Uuid::v4()->toRfc4122(), 'validFrom' => '2026-03-01T08:00:00Z', 'validUntil' => '2026-06-01T08:00:00Z'];

        foreach (['approvals', 'revoke'] as $path) {
            $this->call('POST', self::ROOT."/$team/$path", null, $body);
            self::assertSame(401, $this->code(), $path);
            $this->call('POST', self::ROOT."/$team/$path", $reader, $body);
            self::assertSame(403, $this->code(), $path);
        }
        self::assertSame(0, $this->details($team)['team']['version']);
    }

    public function testPagingAndMalformedInputAreRefused(): void
    {
        $team = $this->team();
        foreach (['?limit=0', '?limit=101', '?offset=-1'] as $query) {
            $this->call('GET', self::ROOT.$query, $this->admin);
            self::assertSame(400, $this->code(), $query);
            self::assertSame('Choose a bounded page of 1–100 records.', $this->message());
        }
        $this->call('GET', self::ROOT.'?limit=many', $this->admin);
        self::assertSame(400, $this->code());
        $this->call('GET', self::ROOT.'/not-a-uuid', $this->admin);
        self::assertSame(400, $this->code());

        $malformed = [
            ['POST', self::ROOT, ['name' => '']],
            ['POST', self::ROOT, ['name' => str_repeat('a', 151)]],
            ['PUT', self::ROOT."/$team", ['name' => 'No version', 'active' => true]],
            ['POST', self::ROOT."/$team/roles", ['expectedVersion' => 0, 'name' => 'Role', 'childrenRole' => 'NONE']],
            ['POST', self::ROOT."/$team/roles", ['expectedVersion' => 0, 'name' => 'Role', 'childrenRole' => 'NONE', 'requiredCredentials' => ['not-a-uuid']]],
            ['POST', self::ROOT."/$team/memberships", ['expectedVersion' => 0, 'volunteerId' => Uuid::v4()->toRfc4122(), 'validFrom' => '2026-03-01', 'validUntil' => '2026-06-01T08:00:00Z']],
            ['POST', self::ROOT."/$team/memberships", ['expectedVersion' => 0, 'volunteerId' => Uuid::v4()->toRfc4122(), 'validUntil' => '2026-06-01T08:00:00Z']],
            ['POST', self::ROOT."/$team/approvals", ['volunteerId' => Uuid::v4()->toRfc4122(), 'roleId' => Uuid::v4()->toRfc4122(), 'validFrom' => '2026-03-01T08:00:00Z', 'validUntil' => '2026-06-01T08:00:00Z']],
            ['POST', self::ROOT."/$team/approvals", ['expectedVersion' => 0, 'volunteerId' => Uuid::v4()->toRfc4122(), 'roleId' => 'not-a-uuid', 'validFrom' => '2026-03-01T08:00:00Z', 'validUntil' => '2026-06-01T08:00:00Z']],
            ['POST', self::ROOT."/$team/approvals", ['expectedVersion' => 0, 'volunteerId' => Uuid::v4()->toRfc4122(), 'roleId' => Uuid::v4()->toRfc4122(), 'validFrom' => '2026-03-01T08:00:00Z']],
            ['POST', self::ROOT."/$team/revoke", ['volunteerId' => Uuid::v4()->toRfc4122(), 'recordId' => Uuid::v4()->toRfc4122()]],
            ['POST', self::ROOT."/$team/revoke", ['expectedVersion' => 0, 'volunteerId' => Uuid::v4()->toRfc4122()]],
            ['POST', self::ROOT."/$team/revoke", ['expectedVersion' => 0, 'volunteerId' => Uuid::v4()->toRfc4122(), 'recordId' => Uuid::v4()->toRfc4122(), 'roleApproval' => 'yes']],
        ];
        foreach ($malformed as [$method, $uri, $body]) {
            $this->call($method, $uri, $this->admin, $body);
            self::assertSame(400, $this->code(), $method.' '.$uri.' '.json_encode($body));
        }
        self::assertSame(1, $this->rows('serving_teams'));
    }
}
