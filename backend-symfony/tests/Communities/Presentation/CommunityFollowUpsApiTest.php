<?php

declare(strict_types=1);

namespace App\Tests\Communities\Presentation;

use Symfony\Component\Uid\Uuid;

/** Port of Java `followupsAreScopedVersionedAndKeepCompletionHistory`, plus scope, replay and input rules. */
final class CommunityFollowUpsApiTest extends CommunitiesApiTestCase
{
    /** @return array<string, string> */
    private function draft(string $person, string $due = '2026-11-01'): array
    {
        return ['requestId' => Uuid::v4()->toRfc4122(), 'personId' => $person, 'note' => 'Call after missed gathering', 'dueOn' => $due];
    }

    public function testFollowUpsAreScopedVersionedAndKeepCompletionHistory(): void
    {
        $group = $this->community('GROUP', 'Home');
        $person = $this->person('Follow up');
        $this->join($group, $person);
        $this->call('POST', self::ROOT."/$group/follow-ups", $this->admin, $this->draft($person));
        self::assertSame(201, $this->code(), $this->message());
        $id = (string) $this->json()['id'];

        $this->call('GET', self::ROOT."/$group/follow-ups", $this->admin);
        self::assertSame([$id, 'Follow up Mabasa', 'Call after missed gathering', '2026-11-01', 'OPEN', 0, $person, $group], [
            $this->json()[0]['id'], $this->json()[0]['name'], $this->json()[0]['note'], $this->json()[0]['dueOn'],
            $this->json()[0]['status'], $this->json()[0]['version'], $this->json()[0]['personId'], $this->json()[0]['communityId'],
        ]);
        $this->call('POST', self::ROOT."/follow-ups/$id/complete", $this->admin, ['expectedVersion' => 0, 'reason' => 'Spoke with member']);
        self::assertSame(204, $this->code());
        $this->call('POST', self::ROOT."/follow-ups/$id/complete", $this->admin, ['expectedVersion' => 0, 'reason' => 'Stale completion']);
        self::assertSame(409, $this->code());
        self::assertSame('These details changed. Refresh and review before saving.', $this->message());
        $this->call('POST', self::ROOT."/follow-ups/$id/complete", $this->admin, ['expectedVersion' => 1, 'reason' => 'Again']);
        self::assertSame(409, $this->code());
        self::assertSame('This follow-up is already complete.', $this->message());

        $this->call('GET', self::ROOT."/$group/follow-ups", $this->admin);
        self::assertSame([], $this->json(), 'completed follow-ups are hidden by default');
        $this->call('GET', self::ROOT."/$group/follow-ups?completed=true", $this->admin);
        self::assertSame(['DONE', 1], [$this->json()[0]['status'], $this->json()[0]['version']]);
        $this->call('GET', self::ROOT."/$group/history", $this->admin);
        self::assertSame(['FOLLOWUP_COMPLETED', 'Spoke with member'], [$this->json()[0]['action'], $this->json()[0]['reason']]);
        self::assertSame(['FOLLOWUP_CREATED', 'Member follow-up recorded.'], [$this->json()[1]['action'], $this->json()[1]['reason']]);
    }

    public function testFollowUpsAreOrderedByDueDateAndReplayed(): void
    {
        $group = $this->community('GROUP', 'Home');
        $person = $this->person('Due');
        $this->join($group, $person);
        $later = $this->draft($person, '2026-12-01');
        $this->call('POST', self::ROOT."/$group/follow-ups", $this->admin, $later);
        $first = (string) $this->json()['id'];
        $this->call('POST', self::ROOT."/$group/follow-ups", $this->admin, $later);
        self::assertSame([201, $first], [$this->code(), $this->json()['id']]);
        $this->call('POST', self::ROOT."/$group/follow-ups", $this->admin, ['note' => 'Different'] + $later);
        self::assertSame(409, $this->code());
        self::assertSame('This request identifier was already used.', $this->message());
        $this->call('POST', self::ROOT."/$group/follow-ups", $this->admin, $this->draft($person, '2026-11-15'));
        $this->call('GET', self::ROOT."/$group/follow-ups", $this->admin);
        self::assertSame(['2026-11-15', '2026-12-01'], array_column($this->json(), 'dueOn'));
        self::assertSame(2, $this->rows('community_followups'));
    }

    public function testOnlyCurrentMembersOfActiveCommunitiesAndRequiredFields(): void
    {
        $group = $this->community('GROUP', 'Home');
        $member = $this->person('Member');
        $membership = $this->join($group, $member);
        $stranger = $this->person('Stranger');
        $this->call('POST', self::ROOT."/$group/follow-ups", $this->admin, $this->draft($stranger));
        self::assertSame(409, $this->code());
        self::assertSame('Follow-up requires a current member of an active community.', $this->message());
        $this->call('POST', self::ROOT."/memberships/$membership/leave", $this->admin, ['expectedVersion' => 0, 'reason' => 'Left']);
        $this->call('POST', self::ROOT."/$group/follow-ups", $this->admin, $this->draft($member));
        self::assertSame(409, $this->code());

        foreach ([
            ['note' => '  '], ['note' => str_repeat('n', 2001)], ['dueOn' => '2026-13-01'], ['dueOn' => '01/11/2026'], ['dueOn' => null],
            ['personId' => 'nope'], ['requestId' => null],
        ] as $change) {
            $this->call('POST', self::ROOT."/$group/follow-ups", $this->admin, $change + $this->draft($member));
            self::assertSame(400, $this->code(), (string) json_encode($change));
        }
        $this->call('POST', self::ROOT."/$group/follow-ups", $this->admin, $this->draft(Uuid::v4()->toRfc4122()));
        self::assertSame(404, $this->code(), 'an unknown person is not found');
        $this->call('POST', self::ROOT.'/'.Uuid::v4()->toRfc4122().'/follow-ups', $this->admin, $this->draft($member));
        self::assertSame(404, $this->code());
        $this->call('POST', self::ROOT.'/follow-ups/'.Uuid::v4()->toRfc4122().'/complete', $this->admin, ['expectedVersion' => 0, 'reason' => 'x']);
        self::assertSame(404, $this->code());
        $this->call('POST', self::ROOT.'/follow-ups/'.Uuid::v4()->toRfc4122().'/complete', $this->admin, ['reason' => 'x']);
        self::assertSame(400, $this->code(), 'a missing version is a 400');
        self::assertSame(0, $this->rows('community_followups'));
    }

    public function testLeadersWorkInTheirOwnCommunityOnlyAndReadersHaveNoAccess(): void
    {
        $group = $this->community('GROUP', 'Home');
        $other = $this->community('GROUP', 'Other');
        $person = $this->person('Member');
        $this->join($group, $person);
        $this->join($other, $person);
        $leader = $this->leader('leader', $this->join($group, $this->person('Leader')));

        $this->call('POST', self::ROOT."/$group/follow-ups", $leader, $this->draft($person));
        self::assertSame(201, $this->code(), $this->message());
        $id = (string) $this->json()['id'];
        $this->call('GET', self::ROOT."/$group/follow-ups", $leader);
        self::assertSame(200, $this->code());
        $this->call('POST', self::ROOT."/follow-ups/$id/complete", $leader, ['expectedVersion' => 0, 'reason' => 'Visited']);
        self::assertSame(204, $this->code());
        $this->call('POST', self::ROOT."/$other/follow-ups", $leader, $this->draft($person));
        self::assertSame(403, $this->code());
        self::assertSame('This community is outside your current leadership scope.', $this->message());
        $this->call('GET', self::ROOT."/$other/follow-ups", $leader);
        self::assertSame(403, $this->code());

        $this->accountWith('reader', ['ministries.read']);
        $reader = $this->login('reader');
        $this->call('GET', self::ROOT."/$group/follow-ups", $reader);
        self::assertSame(403, $this->code(), 'directory readers cannot open follow-ups');
        $this->call('POST', self::ROOT."/$group/follow-ups", $reader, []);
        self::assertSame(403, $this->code(), 'refused before the body is read');
        $this->call('GET', self::ROOT."/$group/follow-ups");
        self::assertSame(401, $this->code());
        $this->call('GET', self::ROOT."/$group/follow-ups?limit=101", $this->admin);
        self::assertSame(400, $this->code());
    }
}
