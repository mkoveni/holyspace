<?php

declare(strict_types=1);

namespace App\Tests\Communities\Application;

use App\Communities\Application\Command\ApproveCommunityNotice\ApproveCommunityNoticeCommand;
use App\Communities\Application\Command\ApproveCommunityNotice\ApproveCommunityNoticeHandler;
use App\Communities\Application\CommunityAccess;
use App\Communities\Application\Contract\MembershipView;
use App\Communities\Application\NoticeReview;
use App\Communities\Domain\Community;
use App\Communities\Domain\CommunityException;
use App\IdentityAccess\Domain\IdentityException;
use App\Tests\Events\Application\LockLog;
use App\Tests\Events\Application\RecordingPeople;
use App\Tests\People\Application\SequentialIds;
use App\Tests\Support\AllowedPermissions;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Notice approval in isolation: permission first, the documented lock order (person, then community), a changed
 * review refused, and nothing queued or audited unless the approval succeeds.
 */
final class NoticeHandlersTest extends TestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string COMMUNITY = 'c0000000-0000-4000-8000-000000000001';
    private const string MEMBERSHIP = 'b0000000-0000-4000-8000-000000000001';
    private const string PERSON = 'a0000000-0000-4000-8000-000000000001';
    private const string CONTACT = 'e0000000-0000-4000-8000-000000000001';
    private const string REQUEST = 'f0000000-0000-4000-8000-000000000001';

    private LockLog $log;
    private RecordingPeople $people;
    private LockingCommunities $communities;
    private ActiveCommunities $reader;
    private MemoryNotices $notices;
    private QueuedDeliveries $deliveries;
    private NoticeTemplates $templates;
    private RecordingAudit $audit;
    private MockClock $clock;
    private AllowedPermissions $permissions;

    protected function setUp(): void
    {
        $this->log = new LockLog();
        $this->people = new RecordingPeople($this->log);
        $this->people->add(self::PERSON);
        $this->communities = new LockingCommunities($this->log);
        $this->communities->insertCommunity(Community::create(self::COMMUNITY, Community::GROUP, 'Home', null, null, null));
        $this->reader = new ActiveCommunities();
        $this->reader->memberships[self::MEMBERSHIP] = new MembershipView(self::MEMBERSHIP, self::COMMUNITY, self::PERSON, new DateTimeImmutable('2026-01-01 UTC'), null, 0);
        $this->notices = new MemoryNotices();
        $this->deliveries = new QueuedDeliveries();
        $this->templates = new NoticeTemplates();
        $this->audit = new RecordingAudit();
        $this->clock = new MockClock('2026-07-05 09:30:00 UTC');
        $this->permissions = new AllowedPermissions(['ministries.manage', 'ministries.communications.send']);
    }

    private function access(): CommunityAccess
    {
        return new CommunityAccess($this->permissions, new SignedInActor(self::ACTOR), $this->reader, $this->clock);
    }

    private function review(): NoticeReview
    {
        return new NoticeReview($this->reader, $this->access(), $this->people, new OneEmail(self::CONTACT), $this->templates, $this->clock);
    }

    private function approve(?string $hash = null, string $evidence = 'Confirmed in person'): string
    {
        $hash ??= $this->review()->review(self::MEMBERSHIP, self::CONTACT, 'Hello')->hash;

        return (new ApproveCommunityNoticeHandler($this->permissions, $this->access(), $this->reader, $this->people, $this->communities, $this->notices, $this->notices, $this->review(), $this->deliveries, $this->audit, new SequentialIds(), $this->clock))
            ->handle(new ApproveCommunityNoticeCommand(self::ACTOR, self::REQUEST, self::MEMBERSHIP, self::CONTACT, 'Hello', $hash, $evidence));
    }

    public function testApprovalLocksThePersonThenTheCommunityAndQueuesTheReviewedContent(): void
    {
        $delivery = $this->approve();

        self::assertSame(['person:'.self::PERSON, 'community:'.self::COMMUNITY], $this->log->locks);
        self::assertCount(1, $this->deliveries->queued);
        $queued = $this->deliveries->queued[0];
        self::assertSame([$delivery, self::REQUEST, 'member@example.org', 'communities.notice', 0], [$queued->id, $queued->communicationId, $queued->recipient, $queued->templateKey, $queued->templateVersion]);
        self::assertSame($delivery, $this->notices->notices[self::REQUEST]->deliveryId);
        self::assertSame(['NOTICE_APPROVED'], $this->audit->actions);
    }

    public function testARepeatedRequestReturnsTheFirstDeliveryAndQueuesNothingMore(): void
    {
        $delivery = $this->approve();

        self::assertSame($delivery, $this->approve());
        self::assertCount(1, $this->deliveries->queued);
        self::assertSame(['NOTICE_APPROVED'], $this->audit->actions);
        $this->expectExceptionObject(CommunityException::conflict('This notice request identifier was already used.'));
        $this->approve(evidence: 'Different evidence');
    }

    public function testAChangedTemplateRevisionRefusesTheApprovalAndWritesNothing(): void
    {
        $hash = $this->review()->review(self::MEMBERSHIP, self::CONTACT, 'Hello')->hash;
        $this->templates->version = 1;

        try {
            $this->approve($hash);
            self::fail('a stale review was accepted');
        } catch (CommunityException $e) {
            self::assertSame('The member, community, contact or template changed. Review the notice again.', $e->getMessage());
        }
        self::assertSame([[], [], []], [$this->deliveries->queued, $this->notices->notices, $this->audit->actions]);
    }

    public function testWithoutThePermissionNothingIsLockedOrWritten(): void
    {
        $this->permissions = new AllowedPermissions(['ministries.manage']);

        try {
            $this->approve('x');
            self::fail('approval without the sending permission');
        } catch (IdentityException) {
        }
        self::assertSame([[], [], []], [$this->log->locks, $this->deliveries->queued, $this->audit->actions]);
    }
}
