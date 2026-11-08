<?php

declare(strict_types=1);

namespace App\Tests\Communities\Presentation;

use Doctrine\DBAL\Exception\DriverException;
use Symfony\Component\Uid\Uuid;

/**
 * Port of the directory, membership and leadership parts of Java `CommunityApiIntegrationTest` (the meeting,
 * follow-up and notice parts arrive with their slices), plus request replay and permission rules.
 */
final class CommunityDirectoryApiTest extends CommunitiesApiTestCase
{
    public function testDefinitionsHaveDistinctKindsAndRelationalParentage(): void
    {
        $ministry = $this->community('MINISTRY', 'Care');
        $body = ['requestId' => Uuid::v4()->toRfc4122(), 'kind' => 'GROUP', 'name' => 'Home group', 'parentId' => $ministry];
        $this->call('POST', self::ROOT, $this->admin, $body);
        self::assertSame(201, $this->code());
        $group = (string) $this->json()['id'];
        $this->call('POST', self::ROOT, $this->admin, $body);
        self::assertSame([201, $group], [$this->code(), $this->json()['id']], 'a repeated request returns the first result');
        $this->call('POST', self::ROOT, $this->admin, ['name' => 'Other'] + $body);
        self::assertSame(409, $this->code());
        self::assertSame('This request identifier was used for different details.', $this->message());

        $this->call('GET', self::ROOT.'?kind=GROUP&search=Home', $this->admin);
        self::assertSame(1, $this->json()['total']);
        self::assertSame(['GROUP', 'Home group', $ministry, '', true, 0], [
            $this->json()['communities'][0]['kind'], $this->json()['communities'][0]['name'], $this->json()['communities'][0]['parentId'],
            $this->json()['communities'][0]['campus'], $this->json()['communities'][0]['active'], $this->json()['communities'][0]['version'],
        ]);
        $this->call('POST', self::ROOT, $this->admin, ['requestId' => Uuid::v4()->toRfc4122(), 'kind' => 'MINISTRY', 'name' => 'Bad parent', 'parentId' => $ministry]);
        self::assertSame(400, $this->code());
        self::assertSame('Choose a ministry or a group with an optional parent ministry.', $this->message());
        $this->call('POST', self::ROOT, $this->admin, ['requestId' => Uuid::v4()->toRfc4122(), 'kind' => 'GROUP', 'name' => 'Nested', 'parentId' => $group]);
        self::assertSame(400, $this->code());
        self::assertSame('Choose a parent ministry.', $this->message());

        $this->call('PUT', self::ROOT."/$group", $this->admin, ['expectedVersion' => 0, 'name' => 'Updated', 'description' => '', 'campus' => 'Central', 'active' => true, 'reason' => 'Renamed group']);
        self::assertSame(204, $this->code());
        $this->call('PUT', self::ROOT."/$group", $this->admin, ['expectedVersion' => 0, 'name' => 'Stale', 'description' => '', 'campus' => '', 'active' => true, 'reason' => 'Old screen']);
        self::assertSame(409, $this->code());
        self::assertSame('These details changed. Refresh and review before saving.', $this->message());

        $this->call('GET', self::ROOT."/$group", $this->admin);
        self::assertSame(['Updated', 'Central', null, 1, true], [$this->json()['community']['name'], $this->json()['community']['campus'], $this->json()['community']['parentId'], $this->json()['community']['version'], $this->json()['canOperate']]);
        $this->call('GET', self::ROOT."/$group/history", $this->admin);
        self::assertSame(['UPDATED', 'Renamed group', $this->adminId], [$this->json()[0]['action'], $this->json()[0]['reason'], $this->json()[0]['actorId']]);
        self::assertSame('CREATED', $this->json()[1]['action']);
    }

    public function testMembershipsEndImmediatelyAndRejoiningPreservesSeparatePeriods(): void
    {
        $group = $this->community('GROUP', 'Home');
        $person = $this->person('Solly');
        $member = $this->join($group, $person);
        $this->call('POST', self::ROOT."/$group/members", $this->admin, ['requestId' => Uuid::v4()->toRfc4122(), 'personId' => $person]);
        self::assertSame(409, $this->code());
        self::assertSame('This person is already a current member.', $this->message());

        $this->call('POST', self::ROOT."/memberships/$member/leave", $this->admin, ['expectedVersion' => 0, 'reason' => 'Moved to another group']);
        self::assertSame(204, $this->code());
        $this->call('POST', self::ROOT."/memberships/$member/leave", $this->admin, ['expectedVersion' => 1, 'reason' => 'Again']);
        self::assertSame(409, $this->code());
        self::assertSame('This membership has already ended.', $this->message());
        $rejoined = $this->join($group, $person);
        self::assertNotSame($member, $rejoined);
        self::assertSame(2, $this->rows('community_memberships'));

        $this->call('GET', self::ROOT."/$group/members?ended=true", $this->admin);
        self::assertSame(2, $this->json()['total']);
        self::assertStringContainsString('Solly Mabasa', $this->body());
        self::assertStringNotContainsString('password', $this->body());
        $this->call('GET', self::ROOT."/$group/members", $this->admin);
        self::assertSame([1, $rejoined, null], [$this->json()['total'], $this->json()['members'][0]['membership']['id'], $this->json()['members'][0]['membership']['endedAt']]);
    }

    public function testARepeatedJoinCannotDuplicateMembershipOrAudit(): void
    {
        $group = $this->community('GROUP', 'Home');
        $person = $this->person('Concurrent');
        $this->join($group, $person);
        $this->call('POST', self::ROOT."/$group/members", $this->admin, ['requestId' => Uuid::v4()->toRfc4122(), 'personId' => $person]);
        self::assertSame(409, $this->code());
        self::assertSame(1, $this->rows('community_memberships'));
        self::assertSame(1, $this->rows('community_history', "action = 'MEMBER_JOINED'"));
    }

    public function testCurrentLeadershipScopesAccessAndEndingMembershipRevokesIt(): void
    {
        $group = $this->community('GROUP', 'Home');
        $other = $this->community('GROUP', 'Other');
        $member = $this->join($group, $this->person('Leader'));
        $leaderId = $this->accountWith('leader', ['ministries.lead']);
        $this->call('POST', self::ROOT."/memberships/$member/leadership", $this->admin, ['requestId' => Uuid::v4()->toRfc4122(), 'expectedVersion' => 0, 'role' => 'LEADER', 'accountId' => $leaderId, 'reason' => 'Verified login identity']);
        self::assertSame(201, $this->code(), $this->message());
        $token = $this->login('leader');

        $this->call('GET', self::ROOT, $token);
        self::assertStringContainsString($group, $this->body());
        self::assertStringNotContainsString($other, $this->body());
        $this->call('GET', self::ROOT."/$group/members", $token);
        self::assertSame(200, $this->code());
        $this->call('GET', self::ROOT."/$group", $token);
        self::assertTrue($this->json()['canOperate']);
        $this->call('GET', self::ROOT."/$other/members", $token);
        self::assertSame(403, $this->code());
        self::assertSame('This community is outside your current leadership scope.', $this->message());
        $this->call('GET', self::ROOT."/$other", $token);
        self::assertSame(403, $this->code());
        $this->call('POST', self::ROOT."/$group/members", $token, ['requestId' => Uuid::v4()->toRfc4122(), 'personId' => $this->person('Other person')]);
        self::assertSame(403, $this->code(), 'leaders do not manage membership');

        $this->call('GET', self::ROOT."/$group/leadership", $this->admin);
        self::assertSame(['LEADER', 'Leader', 'Leader Mabasa', null], [$this->json()[0]['leadership']['role'], $this->json()[0]['accountName'], $this->json()[0]['personName'], $this->json()[0]['leadership']['endedAt']]);

        $this->call('POST', self::ROOT."/memberships/$member/leave", $this->admin, ['expectedVersion' => 0, 'reason' => 'Leadership and membership ended']);
        self::assertSame(204, $this->code());
        $this->call('GET', self::ROOT."/$group/members", $token);
        self::assertSame(403, $this->code());
        self::assertNotNull($this->connection->fetchOne('SELECT ended_at FROM community_leadership'));
    }

    public function testAppointmentsNeedACurrentMembershipAndEndOnce(): void
    {
        $group = $this->community('GROUP', 'Home');
        $member = $this->join($group, $this->person('Appointee'));
        $appoint = ['requestId' => Uuid::v4()->toRfc4122(), 'expectedVersion' => 0, 'role' => 'CO_LEADER', 'reason' => 'Helps the leader'];
        $this->call('POST', self::ROOT."/memberships/$member/leadership", $this->admin, $appoint);
        self::assertSame(201, $this->code());
        $leadership = (string) $this->json()['id'];
        $this->call('POST', self::ROOT."/memberships/$member/leadership", $this->admin, $appoint);
        self::assertSame($leadership, $this->json()['id'], 'replayed');
        $this->call('POST', self::ROOT."/memberships/$member/leadership", $this->admin, ['requestId' => Uuid::v4()->toRfc4122()] + $appoint);
        self::assertSame(409, $this->code());
        self::assertSame('End the current appointment before creating another.', $this->message());
        $this->call('POST', self::ROOT."/memberships/$member/leadership", $this->admin, ['requestId' => Uuid::v4()->toRfc4122(), 'role' => 'CHAIR'] + $appoint);
        self::assertSame(409, $this->code(), 'the open appointment is checked before the role');

        $this->call('GET', self::ROOT."/$group/leadership", $this->admin);
        self::assertSame('No workspace login', $this->json()[0]['accountName']);

        $this->call('POST', self::ROOT."/leadership/$leadership/end", $this->admin, ['expectedVersion' => 0, 'reason' => 'Stepped down']);
        self::assertSame(204, $this->code());
        $this->call('POST', self::ROOT."/leadership/$leadership/end", $this->admin, ['expectedVersion' => 1, 'reason' => 'Again']);
        self::assertSame(409, $this->code());
        self::assertSame('This appointment has already ended.', $this->message());
        $this->call('POST', self::ROOT."/memberships/$member/leadership", $this->admin, ['requestId' => Uuid::v4()->toRfc4122(), 'role' => 'CHAIR'] + $appoint);
        self::assertSame(400, $this->code());
        self::assertSame('Choose leader or co-leader.', $this->message());

        $this->call('POST', self::ROOT."/memberships/$member/leave", $this->admin, ['expectedVersion' => 0, 'reason' => 'Left']);
        $this->call('POST', self::ROOT."/memberships/$member/leadership", $this->admin, ['requestId' => Uuid::v4()->toRfc4122(), 'expectedVersion' => 1] + $appoint);
        self::assertSame(409, $this->code());
        self::assertSame('Leadership requires a current membership.', $this->message());
        self::assertSame(['LEADER_APPOINTED', 'LEADERSHIP_ENDED', 'MEMBER_LEFT'], $this->connection->fetchFirstColumn("SELECT action FROM community_history WHERE action <> 'CREATED' AND action <> 'MEMBER_JOINED' ORDER BY occurred_at"));
    }

    public function testArchivedCommunitiesRejectNewParticipationAndQueriesAreBounded(): void
    {
        $group = $this->community('GROUP', 'Home');
        $person = $this->person('Archived');
        $this->call('PUT', self::ROOT."/$group", $this->admin, ['expectedVersion' => 0, 'name' => 'Home', 'description' => '', 'campus' => '', 'active' => false, 'reason' => 'Group retired']);
        self::assertSame(204, $this->code());
        self::assertSame(1, $this->rows('community_history', "action = 'ARCHIVED'"));
        $this->call('POST', self::ROOT."/$group/members", $this->admin, ['requestId' => Uuid::v4()->toRfc4122(), 'personId' => $person]);
        self::assertSame(409, $this->code());
        self::assertSame('This community is archived.', $this->message());
        $this->call('GET', self::ROOT, $this->admin);
        self::assertSame(0, $this->json()['total']);
        $this->call('GET', self::ROOT.'?archived=true', $this->admin);
        self::assertSame(1, $this->json()['total']);

        foreach (['?limit=101', '?limit=0', '?offset=-1', '?offset=100001', '?kind=TEAM', '?limit=x'] as $query) {
            $this->call('GET', self::ROOT.$query, $this->admin);
            self::assertSame(400, $this->code(), $query);
        }
        $this->call('GET', self::ROOT.'?kind=TEAM', $this->admin);
        self::assertSame('Choose ministry or group.', $this->message());
        $this->call('GET', self::ROOT);
        self::assertSame(401, $this->code());
        $this->call('GET', self::ROOT.'/'.Uuid::v4()->toRfc4122(), $this->admin);
        self::assertSame(404, $this->code());
        self::assertSame('The requested community record was not found.', $this->message());
    }

    public function testDirectoryReadersGetMetadataWithoutRosterAccessAndPeopleLinksStayPaged(): void
    {
        $group = $this->community('GROUP', 'Directory visible');
        $person = $this->person('Member link');
        $this->join($group, $person);
        $this->accountWith('reader', ['people.read', 'ministries.read']);
        $reader = $this->login('reader');

        $this->call('GET', self::ROOT."/$group", $reader);
        self::assertSame([200, false], [$this->code(), $this->json()['canOperate']]);
        $this->call('GET', self::ROOT."/$group/members", $reader);
        self::assertSame(403, $this->code());
        $this->call('GET', self::ROOT."/people/$person", $reader);
        self::assertSame(200, $this->code());
        self::assertSame(['Directory visible', 'GROUP', $group, null], [$this->json()[0]['name'], $this->json()[0]['kind'], $this->json()[0]['communityId'], $this->json()[0]['endedAt']]);
        foreach (['email', 'roster', 'care'] as $secret) {
            self::assertStringNotContainsString($secret, $this->body());
        }
        $this->call('GET', self::ROOT."/people/$person?limit=101", $reader);
        self::assertSame(400, $this->code());

        $this->accountWith('ministry-only', ['ministries.read']);
        $this->call('GET', self::ROOT."/people/$person", $this->login('ministry-only'));
        self::assertSame(403, $this->code(), 'person links also need people.read');
        $this->accountWith('nobody', ['people.read']);
        $this->call('POST', self::ROOT, $this->login('nobody'), []);
        self::assertSame(403, $this->code(), 'the controller needs one of the ministries permissions before the body is read');
    }

    public function testLoginBindingNeedsLiveIdentityManagementPermission(): void
    {
        $member = $this->join($this->community('GROUP', 'Scoped identity'), $this->person('Appointee'));
        $this->accountWith('manager', ['ministries.manage']);
        $manager = $this->login('manager');
        $this->call('POST', self::ROOT."/memberships/$member/leadership", $manager, ['requestId' => Uuid::v4()->toRfc4122(), 'expectedVersion' => 0, 'role' => 'LEADER', 'accountId' => $this->adminId, 'reason' => 'Verified identity']);
        self::assertSame(403, $this->code());
        self::assertSame(0, $this->rows('community_leadership'));

        $disabled = $this->account('disabled', status: 'disabled');
        $this->call('POST', self::ROOT."/memberships/$member/leadership", $this->admin, ['requestId' => Uuid::v4()->toRfc4122(), 'expectedVersion' => 0, 'role' => 'LEADER', 'accountId' => $disabled, 'reason' => 'Verified identity']);
        self::assertSame(400, $this->code());
        self::assertSame('Choose an active login account.', $this->message());
    }

    public function testManagementNeedsTheManagePermissionAndValidInput(): void
    {
        $group = $this->community('GROUP', 'Home');
        $this->accountWith('reader', ['ministries.read']);
        $reader = $this->login('reader');
        $this->call('POST', self::ROOT, $reader, ['requestId' => Uuid::v4()->toRfc4122(), 'kind' => 'GROUP', 'name' => 'Nope']);
        self::assertSame(403, $this->code());
        $this->call('PUT', self::ROOT."/$group", $reader, ['expectedVersion' => 0, 'name' => 'Nope', 'active' => true, 'reason' => 'x']);
        self::assertSame(403, $this->code());

        foreach ([
            [['kind' => 'GROUP', 'name' => 'No request id'], 400],
            [['requestId' => 'nope', 'kind' => 'GROUP', 'name' => 'Bad id'], 400],
            [['requestId' => Uuid::v4()->toRfc4122(), 'kind' => 'GROUP', 'name' => '   '], 400],
            [['requestId' => Uuid::v4()->toRfc4122(), 'kind' => 'GROUP', 'name' => str_repeat('n', 151)], 400],
            [['requestId' => Uuid::v4()->toRfc4122(), 'kind' => 'TEAM', 'name' => 'Team'], 400],
            [['requestId' => Uuid::v4()->toRfc4122(), 'kind' => 'GROUP', 'name' => 'Orphan', 'parentId' => Uuid::v4()->toRfc4122()], 404],
        ] as [$body, $status]) {
            $this->call('POST', self::ROOT, $this->admin, $body);
            self::assertSame($status, $this->code(), (string) json_encode($body));
        }
        $this->call('PUT', self::ROOT."/$group", $this->admin, ['name' => 'Missing version', 'active' => true, 'reason' => 'x']);
        self::assertSame(400, $this->code(), 'a missing version is a 400, never a silent 0');
        $this->call('PUT', self::ROOT."/$group", $this->admin, ['expectedVersion' => 0, 'name' => 'No reason', 'active' => true, 'reason' => ' ']);
        self::assertSame(400, $this->code());
        $this->call('POST', self::ROOT."/$group/members", $this->admin, ['requestId' => Uuid::v4()->toRfc4122(), 'personId' => Uuid::v4()->toRfc4122()]);
        self::assertSame(400, $this->code());
        self::assertSame('Choose an available person.', $this->message());
        $this->call('POST', self::ROOT.'/memberships/'.Uuid::v4()->toRfc4122().'/leave', $this->admin, ['expectedVersion' => 0, 'reason' => 'x']);
        self::assertSame(404, $this->code());
    }

    public function testDatabaseConstraintsRejectOverlappingMembershipEvenOutsideTheApplication(): void
    {
        $group = $this->community('GROUP', 'Period protection');
        $person = $this->person('Period');
        $member = $this->join($group, $person);
        $started = (string) $this->connection->fetchOne('SELECT started_at FROM community_memberships WHERE id = ?', [$member]);
        try {
            $this->connection->executeStatement(
                "INSERT INTO community_memberships (id, community_id, person_id, started_at) VALUES (?, ?, ?, CAST(? AS TIMESTAMPTZ) + interval '1 second')",
                [Uuid::v4()->toRfc4122(), $group, $person, $started],
            );
            self::fail('The exclusion constraint must refuse an overlapping period.');
        } catch (DriverException $expected) {
            self::assertStringContainsString('community_membership_period_excl', $expected->getMessage());
        }
        self::assertSame(1, $this->rows('community_memberships'));
    }
}
