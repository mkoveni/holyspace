<?php

declare(strict_types=1);

namespace App\Tests\Communities\Presentation;

use App\Communications\Application\Contract\DeliveryReader;
use App\Communications\Application\Contract\DeliverySnapshot;
use App\Communities\Infrastructure\CommunityDeliveryEligibility;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Port of Java `reviewedNoticesAreFrozenAndMembershipRevocationBlocksDispatch`, plus replay, re-review after a change,
 * leader scope, permissions and input rules.
 */
final class CommunityMessagingApiTest extends CommunitiesApiTestCase
{
    private const string MESSAGE = 'Welcome <b>friend</b>';

    private string $group;
    private string $person;
    private string $member;
    private string $contact;

    protected function setUp(): void
    {
        parent::setUp();
        $this->group = $this->community('GROUP', 'Home');
        $this->person = $this->person('Recipient');
        $this->member = $this->join($this->group, $this->person);
        $this->call('GET', "/api/people/{$this->person}", $this->admin);
        $this->call('POST', "/api/people/{$this->person}/contacts", $this->admin, ['expectedVersion' => (int) $this->json()['person']['version'], 'kind' => 'EMAIL', 'value' => 'member@example.org', 'primary' => true]);
        self::assertSame(201, $this->code(), $this->message());
        $this->contact = (string) $this->json()['id'];
    }

    /** @return array<int|string, mixed> */
    private function review(?string $token = null): array
    {
        $this->call('POST', self::ROOT."/memberships/{$this->member}/notice-review", $token ?? $this->admin, ['contactId' => $this->contact, 'message' => self::MESSAGE]);
        self::assertSame(200, $this->code(), $this->message());

        return $this->json();
    }

    /** @return array<string, string> */
    private function approval(string $hash, ?string $request = null): array
    {
        return ['requestId' => $request ?? Uuid::v4()->toRfc4122(), 'contactId' => $this->contact, 'message' => self::MESSAGE, 'reviewHash' => $hash, 'evidence' => 'Recipient confirmed own address and permission'];
    }

    private function snapshot(string $delivery): DeliverySnapshot
    {
        $reader = self::getContainer()->get(DeliveryReader::class);
        self::assertInstanceOf(DeliveryReader::class, $reader);
        $snapshot = $reader->delivery($delivery);
        self::assertNotNull($snapshot);

        return $snapshot;
    }

    private function eligible(string $delivery): bool
    {
        $eligibility = self::getContainer()->get(CommunityDeliveryEligibility::class);
        self::assertInstanceOf(CommunityDeliveryEligibility::class, $eligibility);

        return $eligibility->eligible($this->snapshot($delivery), new DateTimeImmutable());
    }

    public function testReviewedNoticesAreFrozenAndMembershipRevocationBlocksDispatch(): void
    {
        $this->call('GET', self::ROOT."/memberships/{$this->member}/contacts", $this->admin);
        self::assertSame([['id' => $this->contact, 'value' => 'member@example.org']], $this->json());

        $review = $this->review();
        self::assertStringContainsString('&lt;b&gt;', (string) $review['html']);
        self::assertSame(['member@example.org', 'A message from Home', 0], [$review['recipient'], $review['subject'], $review['templateVersion']]);
        self::assertSame(64, \strlen((string) $review['hash']));
        $approval = $this->approval((string) $review['hash']);
        $this->call('POST', self::ROOT."/memberships/{$this->member}/notices", $this->admin, $approval);
        self::assertSame(201, $this->code(), $this->message());
        $delivery = (string) $this->json()['id'];
        $this->call('POST', self::ROOT."/memberships/{$this->member}/notices", $this->admin, $approval);
        self::assertSame([201, $delivery], [$this->code(), $this->json()['id']], 'a repeated request returns the first delivery');

        $snapshot = $this->snapshot($delivery);
        self::assertSame([$approval['requestId'], 'member@example.org', 'communities.notice', 'QUEUED', 'text/html'], [$snapshot->communicationId, $snapshot->recipient, $snapshot->templateKey, $snapshot->status, $snapshot->contentType]);
        self::assertTrue($this->eligible($delivery));

        $this->call('POST', self::ROOT."/memberships/{$this->member}/leave", $this->admin, ['expectedVersion' => 0, 'reason' => 'Membership ended']);
        self::assertSame(204, $this->code());
        self::assertFalse($this->eligible($delivery), 'the membership ended after approval');
        self::assertSame(1, $this->rows('community_notices'));
        self::assertSame(1, $this->rows('communication_email_deliveries'));

        $this->call('GET', self::ROOT."/{$this->group}/deliveries", $this->admin);
        self::assertSame(200, $this->code(), $this->message());
        self::assertStringContainsString('QUEUED', $this->body());
        self::assertStringNotContainsString('member@example.org', $this->body());
        self::assertStringNotContainsString('Welcome', $this->body());
        self::assertSame([$delivery, $approval['requestId'], 0, false], [$this->json()[0]['id'], $this->json()[0]['communicationId'], $this->json()[0]['attempts'], $this->json()[0]['captured']]);

        $this->call('GET', self::ROOT."/{$this->group}/history", $this->admin);
        self::assertSame(['NOTICE_APPROVED', 'Verified member notice queued.', $approval['requestId']], [$this->json()[1]['action'], $this->json()[1]['reason'], $this->json()[1]['targetId']]);

        $this->call('POST', self::ROOT."/memberships/{$this->member}/notice-review", $this->admin, ['contactId' => $this->contact, 'message' => self::MESSAGE]);
        self::assertSame([409, 'Notices require a current, available member of an active community.'], [$this->code(), $this->message()]);
    }

    public function testEligibilityRechecksTheCommunityApproverAndAddress(): void
    {
        $this->call('POST', self::ROOT."/memberships/{$this->member}/notices", $this->admin, $this->approval((string) $this->review()['hash']));
        $delivery = (string) $this->json()['id'];
        self::assertTrue($this->eligible($delivery));

        $this->connection->executeStatement('UPDATE communities SET version = version + 1 WHERE id = ?', [$this->group]);
        self::assertFalse($this->eligible($delivery), 'the community changed after approval');
        $this->connection->executeStatement('UPDATE communities SET version = version - 1 WHERE id = ?', [$this->group]);
        self::assertTrue($this->eligible($delivery));

        $this->connection->executeStatement('UPDATE communication_email_deliveries SET recipient = ? WHERE id = ?', ['other@example.org', $delivery]);
        self::assertFalse($this->eligible($delivery), 'the recipient is not the reviewed address');
        $this->connection->executeStatement('UPDATE communication_email_deliveries SET recipient = ? WHERE id = ?', ['member@example.org', $delivery]);

        $this->connection->executeStatement('UPDATE communities SET active = FALSE WHERE id = ?', [$this->group]);
        self::assertFalse($this->eligible($delivery), 'the community was archived');
    }

    public function testChangesAfterReviewRequireReviewingAgain(): void
    {
        $hash = (string) $this->review()['hash'];
        $this->call('PUT', self::ROOT."/{$this->group}", $this->admin, ['expectedVersion' => 0, 'name' => 'Renamed', 'description' => '', 'campus' => '', 'active' => true, 'reason' => 'Renamed group']);
        self::assertSame(204, $this->code());
        $this->call('POST', self::ROOT."/memberships/{$this->member}/notices", $this->admin, $this->approval($hash));
        self::assertSame([409, 'The member, community, contact or template changed. Review the notice again.'], [$this->code(), $this->message()]);
        self::assertSame(0, $this->rows('community_notices'));
        self::assertSame(0, $this->rows('communication_email_deliveries'));
        self::assertNotSame($hash, $this->review()['hash']);
    }

    public function testARequestIdCannotBeReusedForDifferentDetails(): void
    {
        $hash = (string) $this->review()['hash'];
        $request = Uuid::v4()->toRfc4122();
        $this->call('POST', self::ROOT."/memberships/{$this->member}/notices", $this->admin, $this->approval($hash, $request));
        self::assertSame(201, $this->code(), $this->message());
        $this->call('POST', self::ROOT."/memberships/{$this->member}/notices", $this->admin, ['evidence' => 'Different evidence'] + $this->approval($hash, $request));
        self::assertSame([409, 'This notice request identifier was already used.'], [$this->code(), $this->message()]);
        self::assertSame(1, $this->rows('community_notices'));
    }

    public function testTheAddressMustBeTheMembersCurrentEmail(): void
    {
        $this->call('POST', self::ROOT."/memberships/{$this->member}/notice-review", $this->admin, ['contactId' => Uuid::v4()->toRfc4122(), 'message' => 'Hello']);
        self::assertSame([409, "Choose this member's current email address."], [$this->code(), $this->message()]);
        $this->call('GET', self::ROOT.'/memberships/'.Uuid::v4()->toRfc4122().'/contacts', $this->admin);
        self::assertSame(404, $this->code());
    }

    public function testLeadersReachOnlyTheCommunitiesTheyLead(): void
    {
        $account = $this->accountWith('sender', ['ministries.lead', 'ministries.communications.send']);
        $this->call('POST', self::ROOT."/memberships/{$this->member}/leadership", $this->admin, ['requestId' => Uuid::v4()->toRfc4122(), 'expectedVersion' => 0, 'role' => 'LEADER', 'accountId' => $account, 'reason' => 'Verified login identity']);
        self::assertSame(201, $this->code(), $this->message());
        $leader = $this->login('sender');
        $this->call('POST', self::ROOT."/memberships/{$this->member}/notices", $leader, $this->approval((string) $this->review($leader)['hash']));
        self::assertSame(201, $this->code(), $this->message());
        $this->call('GET', self::ROOT."/{$this->group}/deliveries", $leader);
        self::assertSame(200, $this->code(), $this->message());

        $other = $this->community('GROUP', 'Elsewhere');
        $outsider = $this->join($other, $this->person('Outsider'));
        $this->call('GET', self::ROOT."/memberships/$outsider/contacts", $leader);
        self::assertSame([403, 'This community is outside your current leadership scope.'], [$this->code(), $this->message()]);
        $this->call('GET', self::ROOT."/$other/deliveries", $leader);
        self::assertSame(403, $this->code());
    }

    public function testPermissionsAreEnforced(): void
    {
        $this->accountWith('reader', ['ministries.read', 'ministries.manage']);
        $reader = $this->login('reader');
        $routes = [
            ['GET', self::ROOT."/memberships/{$this->member}/contacts", null],
            ['POST', self::ROOT."/memberships/{$this->member}/notice-review", ['contactId' => $this->contact, 'message' => 'x']],
            ['POST', self::ROOT."/memberships/{$this->member}/notices", $this->approval('x')],
            ['GET', self::ROOT."/{$this->group}/deliveries", null],
        ];
        foreach ($routes as [$method, $uri, $body]) {
            $this->call($method, $uri, null, $body);
            self::assertSame(401, $this->code(), $uri);
            $this->call($method, $uri, $reader, $body);
            self::assertSame(403, $this->code(), $uri);
        }
        self::assertSame(0, $this->rows('community_notices'));
    }

    public function testMalformedInputIsRejected(): void
    {
        $hash = (string) $this->review()['hash'];
        $bodies = [
            ['requestId' => 'nope'] + $this->approval($hash),
            ['contactId' => null] + $this->approval($hash),
            ['message' => ' '] + $this->approval($hash),
            ['message' => str_repeat('x', 5001)] + $this->approval($hash),
            ['reviewHash' => ''] + $this->approval($hash),
            ['evidence' => str_repeat('x', 501)] + $this->approval($hash),
        ];
        foreach ($bodies as $body) {
            $this->call('POST', self::ROOT."/memberships/{$this->member}/notices", $this->admin, $body);
            self::assertSame(400, $this->code(), (string) json_encode($body));
        }
        $this->call('POST', self::ROOT."/memberships/{$this->member}/notice-review", $this->admin, ['contactId' => $this->contact]);
        self::assertSame(400, $this->code());
        $this->call('GET', self::ROOT.'/memberships/not-a-uuid/contacts', $this->admin);
        self::assertSame(400, $this->code());
        $this->call('GET', self::ROOT."/{$this->group}/deliveries?limit=101", $this->admin);
        self::assertSame(400, $this->code());
        self::assertSame(0, $this->rows('community_notices'));
    }
}
