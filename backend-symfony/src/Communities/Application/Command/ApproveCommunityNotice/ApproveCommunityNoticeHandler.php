<?php

declare(strict_types=1);

namespace App\Communities\Application\Command\ApproveCommunityNotice;

use App\Communications\Application\Contract\DeliverySnapshot;
use App\Communications\Application\Contract\EmailDeliveries;
use App\Communities\Application\CommunityAccess;
use App\Communities\Application\CommunityHash;
use App\Communities\Application\Contract\CommunityAuditWriter;
use App\Communities\Application\Contract\CommunityReader;
use App\Communities\Application\Contract\NoticeReader;
use App\Communities\Application\NoticeReview;
use App\Communities\Domain\CommunityException;
use App\Communities\Domain\CommunityNotice;
use App\Communities\Domain\CommunityRules;
use App\Communities\Domain\Repository\CommunityRepository;
use App\Communities\Domain\Repository\NoticeRepository;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

/**
 * Java `CommunityMessaging.approve`: freezes a reviewed notice into a queued delivery. Lock order: the member's
 * person, then the community row. Repeating a request returns the first delivery; the content is reviewed again
 * under the locks and must still match the hash the approver saw.
 */
final readonly class ApproveCommunityNoticeHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private CommunityAccess $access,
        private CommunityReader $reader,
        private PersonDirectory $people,
        private CommunityRepository $communities,
        private NoticeReader $notices,
        private NoticeRepository $repository,
        private NoticeReview $review,
        private EmailDeliveries $deliveries,
        private CommunityAuditWriter $audit,
        private IdGenerator $ids,
        private ClockInterface $clock,
    ) {
    }

    public function handle(ApproveCommunityNoticeCommand $command): string
    {
        $this->auth->requireActorPermission($command->actorId, CommunityAccess::SEND);
        $this->access->actor($command->actorId);
        $evidence = CommunityRules::text($command->evidence, 500);
        $requestHash = CommunityHash::of($command->actorId, $command->membershipId, $command->contactId, $command->message, $command->reviewHash, $evidence);

        $before = $this->reader->membership($command->membershipId) ?? throw CommunityException::missing();
        $this->people->lock($before->personId) ?? throw CommunityException::missing();
        $this->communities->lockCommunity($before->communityId) ?? throw CommunityException::missing();
        $this->access->require($before->communityId);
        $previous = $this->notices->notice($command->requestId);
        if (null !== $previous) {
            if (!hash_equals($previous->requestHash, $requestHash)) {
                throw CommunityException::conflict('This notice request identifier was already used.');
            }

            return $previous->deliveryId;
        }
        $reviewed = $this->review->review($command->membershipId, $command->contactId, $command->message);
        if (null === $command->reviewHash || !hash_equals($reviewed->hash, $command->reviewHash)) {
            throw CommunityException::conflict('The member, community, contact or template changed. Review the notice again.');
        }
        $now = $this->clock->now();
        $delivery = $this->ids->uuid();
        $this->repository->insert(CommunityNotice::approve($command->requestId, $before->communityId, $command->membershipId, $command->contactId, $requestHash, $reviewed->hash, $evidence, $command->actorId, $now, $delivery));
        $this->deliveries->queue('communities', new DeliverySnapshot(
            $delivery,
            $command->requestId,
            null,
            $command->contactId,
            $reviewed->recipient,
            $reviewed->subject,
            $reviewed->html,
            $reviewed->hash,
            $command->actorId,
            $now,
            $now,
            'QUEUED',
            0,
            '<'.$delivery.'@onechurch.delivery>',
            null,
            null,
            null,
            '',
            false,
            0,
            'text/html',
            NoticeReview::TEMPLATE,
            $reviewed->templateVersion,
        ));
        $this->audit->record($before->communityId, $command->requestId, 'NOTICE_APPROVED', 'Verified member notice queued.', $command->actorId, $now);

        return $delivery;
    }
}
