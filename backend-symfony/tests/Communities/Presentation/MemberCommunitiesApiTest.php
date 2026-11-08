<?php

declare(strict_types=1);

namespace App\Tests\Communities\Presentation;

use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/** Port of Java `MemberCommunitiesApiIntegrationTest`, plus paging, headers and the meeting list window. */
final class MemberCommunitiesApiTest extends CommunitiesApiTestCase
{
    use GroupMeetingFixtures;

    private string $account;
    private string $member;
    private string $self;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createVenue();
        $this->account = $this->account('member');
        $this->member = $this->login('member');
        $this->self = $this->person('Member');
    }

    private function link(bool $active, int $version): void
    {
        $this->call('PUT', "/api/member-access/{$this->account}", $this->admin, ['personId' => $this->self, 'active' => $active, 'expectedVersion' => $version, 'evidence' => 'Identity verified in person']);
        self::assertSame(204, $this->code());
    }

    private function own(string $id): string
    {
        return "/api/member/communities/$id";
    }

    private function meetingPath(string $id): string
    {
        return "/api/member/group-meetings/$id";
    }

    private function at(string $modifier): string
    {
        return Timestamps::toDatabase(new DateTimeImmutable($modifier));
    }

    public function testVerifiedMembershipDefinesScopeWithoutStaffPermissions(): void
    {
        $group = $this->community('GROUP', 'Home group');
        $ministry = $this->community('MINISTRY', 'Welcome ministry');
        $hidden = $this->community('GROUP', 'Private leaders');
        $this->join($group, $this->self);
        $this->join($ministry, $this->self);

        $this->call('GET', '/api/member/communities');
        self::assertSame(401, $this->code());
        $this->call('GET', '/api/member/communities', $this->member);
        self::assertSame(['verified' => false, 'items' => [], 'hasMore' => false], $this->json());

        $this->link(true, 0);
        $this->call('GET', '/api/member/communities?limit=1', $this->member);
        self::assertSame([200, true, true, 'Home group'], [$this->code(), $this->json()['verified'], $this->json()['hasMore'], $this->json()['items'][0]['name']]);
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        $this->call('GET', '/api/member/communities?kind=GROUP', $this->member);
        self::assertStringContainsString('Home group', $this->body());
        self::assertStringNotContainsString('Welcome ministry', $this->body());
        self::assertStringNotContainsString('Private leaders', $this->body());
        self::assertSame(['GROUP', 'MEMBER', null, ''], [$this->json()['items'][0]['kind'], $this->json()['items'][0]['role'], $this->json()['items'][0]['parentName'], $this->json()['items'][0]['campus']]);

        $this->call('GET', $this->own($hidden), $this->member);
        self::assertSame(404, $this->code());
        $this->call('GET', self::ROOT."/$group/members", $this->member);
        self::assertSame(403, $this->code());
        foreach (['?limit=101', '?limit=0', '?offset=-1', '?kind=DEPARTMENT', '?limit=x'] as $query) {
            $this->call('GET', '/api/member/communities'.$query, $this->member);
            self::assertSame(400, $this->code(), $query);
        }
        $this->call('GET', '/api/member/communities/not-a-uuid', $this->member);
        self::assertSame(400, $this->code());
    }

    public function testParentNamesDoNotGrantMembershipAndLeaderProjectionIsMinimal(): void
    {
        $parent = $this->community('MINISTRY', 'Care ministry');
        $group = $this->community('GROUP', 'Care circle', $parent);
        $this->join($group, $this->self);
        $this->link(true, 0);
        $leaderPerson = $this->person('Naledi');
        $leaderMembership = $this->join($group, $leaderPerson);
        $this->call('POST', self::ROOT."/memberships/$leaderMembership/leadership", $this->admin, ['requestId' => Uuid::v4()->toRfc4122(), 'expectedVersion' => 0, 'role' => 'LEADER', 'reason' => 'Appointed by ministry']);
        self::assertSame(201, $this->code(), $this->message());
        $appointment = (string) $this->json()['id'];

        $this->call('GET', $this->own($group), $this->member);
        self::assertSame(200, $this->code(), $this->message());
        self::assertSame('Care ministry', $this->json()['community']['parentName']);
        self::assertSame([['name' => 'Naledi Mabasa', 'role' => 'LEADER']], $this->json()['leaders']);
        self::assertFalse($this->json()['hasMoreLeaders']);
        foreach ([$leaderPerson, $leaderMembership, $appointment, 'accountId', 'personId', 'contacts', 'evidence'] as $secret) {
            self::assertStringNotContainsString($secret, $this->body());
        }
        $this->call('GET', $this->own($parent), $this->member);
        self::assertSame(404, $this->code(), 'a parent ministry name grants no parent membership');
        $this->call('GET', $this->own($group).'?leaderLimit=0', $this->member);
        self::assertSame(400, $this->code());

        $this->connection->executeStatement('UPDATE community_leadership SET ended_at = ? WHERE id = ?', [$this->at('now'), $appointment]);
        $this->call('GET', $this->own($group), $this->member);
        self::assertStringNotContainsString('Naledi Mabasa', $this->body());
    }

    public function testLeadersArePagedAndTheCallersOwnRoleIsShown(): void
    {
        $group = $this->community('GROUP', 'Home group');
        $own = $this->join($group, $this->self);
        $this->link(true, 0);
        foreach ([$own, $this->join($group, $this->person('Second'))] as $membership) {
            $this->call('POST', self::ROOT."/memberships/$membership/leadership", $this->admin, ['requestId' => Uuid::v4()->toRfc4122(), 'expectedVersion' => 0, 'role' => 'LEADER', 'reason' => 'Appointed']);
            self::assertSame(201, $this->code(), $this->message());
        }
        $this->call('GET', $this->own($group).'?leaderLimit=1', $this->member);
        self::assertSame(['LEADER', 1, true], [$this->json()['community']['role'], \count($this->json()['leaders']), $this->json()['hasMoreLeaders']]);
        $this->call('GET', $this->own($group).'?leaderLimit=1&leaderOffset=1', $this->member);
        self::assertSame([1, false], [\count($this->json()['leaders']), $this->json()['hasMoreLeaders']]);
    }

    public function testEndedArchivedFutureAndRevokedMembershipsCannotBeRead(): void
    {
        $group = $this->community('GROUP', 'Current group');
        $membership = $this->join($group, $this->self);
        $this->link(true, 0);
        $this->connection->executeStatement('UPDATE community_memberships SET ended_at = ? WHERE id = ?', [$this->at('now'), $membership]);
        $this->call('GET', $this->own($group), $this->member);
        self::assertSame(404, $this->code());
        $this->call('GET', '/api/member/communities', $this->member);
        self::assertStringNotContainsString($group, $this->body());

        $current = $this->join($group, $this->self);
        $this->connection->executeStatement('UPDATE community_memberships SET started_at = ? WHERE id = ?', [$this->at('+1 day'), $current]);
        $this->call('GET', $this->own($group), $this->member);
        self::assertSame(404, $this->code(), 'a future membership');
        $this->connection->executeStatement('UPDATE community_memberships SET started_at = ? WHERE id = ?', [$this->at('now'), $current]);
        $this->connection->executeStatement('UPDATE communities SET active = FALSE WHERE id = ?', [$group]);
        $this->call('GET', $this->own($group), $this->member);
        self::assertSame(404, $this->code(), 'an archived community');
        $this->connection->executeStatement('UPDATE communities SET active = TRUE WHERE id = ?', [$group]);
        $this->call('GET', $this->own($group), $this->member);
        self::assertSame(200, $this->code());

        $this->link(false, 1);
        $this->call('GET', $this->own($group), $this->member);
        self::assertSame(404, $this->code(), 'a revoked member link');
        $this->call('GET', '/api/member/group-meetings', $this->member);
        self::assertSame(['verified' => false, 'items' => [], 'hasMore' => false], $this->json());
    }

    public function testMeetingsExposeOnlyOwnAttendanceAndGetsNeverPrepareRosters(): void
    {
        $group = $this->community('GROUP', 'Home group');
        $this->join($group, $this->self);
        $this->join($group, $this->person('Another'));
        $this->link(true, 0);
        $meeting = $this->meeting($group);

        $this->call('GET', $this->meetingPath($meeting), $this->member);
        self::assertSame([200, 'NOT_PREPARED', 'OPEN', 'Lounge', 'UTC', 'Home group'], [$this->code(), $this->json()['attendance'], $this->json()['status'], $this->json()['venue'], $this->json()['timeZone'], $this->json()['communityName']]);
        foreach (['Another', 'personId', 'rosterId', 'occurrenceId', $this->occurrence($meeting)] as $secret) {
            self::assertStringNotContainsString($secret, $this->body());
        }
        $this->call('GET', "/api/member/group-meetings?communityId=$group", $this->member);
        self::assertSame([true, $meeting, false], [$this->json()['verified'], $this->json()['items'][0]['id'], $this->json()['hasMore']]);
        self::assertSame(0, $this->rows('community_roster'));

        $this->prepare($meeting, 0);
        $this->noContent();
        $this->call('GET', $this->meetingPath($meeting), $this->member);
        self::assertSame('UNRECORDED', $this->json()['attendance']);
        $this->mark($this->rosterRow($meeting, $this->self), 0, 'PRESENT');
        $this->noContent();
        $this->call('GET', $this->meetingPath($meeting), $this->member);
        self::assertSame('PRESENT', $this->json()['attendance']);
        self::assertStringNotContainsString('Another', $this->body());

        $this->call('GET', self::ROOT."/meetings/$meeting/roster", $this->member);
        self::assertSame(403, $this->code());
        $this->call('POST', $this->meetingPath($meeting), $this->member, ['status' => 'PRESENT']);
        self::assertSame(405, $this->code());
    }

    public function testOtherMeetingsAndMeetingsPredatingMembershipRemainPrivate(): void
    {
        $own = $this->community('GROUP', 'Own group');
        $other = $this->community('GROUP', 'Other group');
        $this->join($own, $this->self);
        $this->link(true, 0);
        $meeting = $this->meeting($own);

        $this->call('GET', "/api/member/group-meetings?communityId=$other", $this->member);
        self::assertSame(404, $this->code());
        $this->call('GET', '/api/member/group-meetings?communityId=nope', $this->member);
        self::assertSame(400, $this->code());
        $otherMeeting = Uuid::v4()->toRfc4122();
        $this->connection->insert('community_meetings', ['id' => $otherMeeting, 'community_id' => $other, 'occurrence_id' => Uuid::v4()->toRfc4122(), 'scheduled_at' => $this->at('+1 day'), 'status' => 'OPEN']);
        $this->call('GET', $this->meetingPath($otherMeeting), $this->member);
        self::assertSame(404, $this->code());

        $this->connection->executeStatement('UPDATE community_meetings SET scheduled_at = ? WHERE id = ?', [$this->at('-2 days'), $meeting]);
        $this->call('GET', $this->meetingPath($meeting), $this->member);
        self::assertSame(404, $this->code(), 'a meeting before the membership began');
    }

    public function testCancelledSchedulesAndNotListedAttendanceStayDistinct(): void
    {
        $group = $this->community('GROUP', 'Home group');
        $this->join($group, $this->person('Leader'));
        $this->link(true, 0);
        $meeting = $this->meeting($group);
        $this->prepare($meeting, 0);
        $this->noContent();
        $this->join($group, $this->self);

        $this->call('GET', $this->meetingPath($meeting), $this->member);
        self::assertSame(['NOT_LISTED', 'OPEN'], [$this->json()['attendance'], $this->json()['status']]);
        $this->connection->executeStatement("UPDATE event_occurrences SET status = 'CANCELLED' WHERE id = ?", [$this->occurrence($meeting)]);
        $this->call('GET', $this->meetingPath($meeting), $this->member);
        self::assertSame(['NOT_LISTED', 'CANCELLED'], [$this->json()['attendance'], $this->json()['status']]);
    }

    public function testTheMeetingListIsBoundedToTheNext180DaysAndPaged(): void
    {
        $group = $this->community('GROUP', 'Home group');
        $this->join($group, $this->self);
        $this->link(true, 0);
        $this->meeting($group, $this->schedule(3, 'WEEKLY'));
        $this->call('GET', '/api/member/group-meetings?limit=2', $this->member);
        self::assertSame([2, true], [\count($this->json()['items']), $this->json()['hasMore']]);
        $this->call('GET', '/api/member/group-meetings?limit=2&offset=2', $this->member);
        self::assertSame([1, false], [\count($this->json()['items']), $this->json()['hasMore']]);

        $this->connection->executeStatement('UPDATE community_meetings SET scheduled_at = ? WHERE community_id = ?', [$this->at('+181 days'), $group]);
        $this->call('GET', '/api/member/group-meetings', $this->member);
        self::assertSame([], $this->json()['items']);
        $this->call('GET', '/api/member/group-meetings?limit=101', $this->member);
        self::assertSame(400, $this->code());
    }
}
