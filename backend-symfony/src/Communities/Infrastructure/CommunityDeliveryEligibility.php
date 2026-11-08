<?php

declare(strict_types=1);

namespace App\Communities\Infrastructure;

use App\Communications\Application\Contract\DeliveryEligibility;
use App\Communications\Application\Contract\DeliverySnapshot;
use App\Communities\Application\CommunityAccess;
use App\Communities\Application\Contract\CommunityReader;
use App\Communities\Application\Contract\NoticeReader;
use App\Communities\Application\NoticeReview;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\IdentityAccess\Application\Contract\ResponsibleAccountDirectory;
use App\People\Application\Contract\PersonDirectory;
use App\People\Application\Contract\PersonEmailDirectory;
use DateTimeImmutable;

/**
 * Java `CommunityDeliveryEligibility`. Before each send the approver must still hold the sending permission and manage
 * or currently lead the community, and the membership, community, person and address must be exactly as reviewed.
 */
final readonly class CommunityDeliveryEligibility implements DeliveryEligibility
{
    public function __construct(
        private CommunityReader $reader,
        private NoticeReader $notices,
        private PermissionChecker $auth,
        private ResponsibleAccountDirectory $accounts,
        private CommunityAccess $access,
        private PersonDirectory $people,
        private PersonEmailDirectory $emails,
    ) {
    }

    public function sourceContext(): string
    {
        return 'communities';
    }

    public function authorize(DeliverySnapshot $delivery, string $actor, string $operation): void
    {
        $this->auth->requireActorPermission($actor, CommunityAccess::SEND);
        $notice = $this->notices->notice($delivery->communicationId);
        if (null !== $notice) {
            $this->access->require($notice->communityId);
        }
    }

    public function eligible(DeliverySnapshot $delivery, DateTimeImmutable $at): bool
    {
        if (!$this->accounts->eligible($delivery->approvedBy, CommunityAccess::SEND) || null === $delivery->templateVersion
            || NoticeReview::TEMPLATE !== $delivery->templateKey) {
            return false;
        }
        $notice = $this->notices->notice($delivery->communicationId);
        if (null === $notice || $notice->deliveryId !== $delivery->id || $notice->actorId !== $delivery->approvedBy
            || $notice->reviewHash !== $delivery->reviewHash || $notice->contactId !== $delivery->contactId) {
            return false;
        }
        $membership = $this->reader->membership($notice->membershipId);
        $community = $this->reader->community($notice->communityId);
        if (null === $membership || null === $community || !$community->active || !$membership->covers($at)
            || $membership->communityId !== $community->id) {
            return false;
        }
        if (!$this->accounts->eligible($delivery->approvedBy, CommunityAccess::MANAGE)
            && !($this->accounts->eligible($delivery->approvedBy, CommunityAccess::LEAD) && $this->reader->leads($community->id, $delivery->approvedBy, $at))) {
            return false;
        }
        if (!($this->people->get($membership->personId)->available ?? false)) {
            return false;
        }
        foreach ($this->emails->active($membership->personId) as $email) {
            if ($email->id === $delivery->contactId && $email->value === $delivery->recipient) {
                return hash_equals(NoticeReview::hash($membership, $community, $delivery->contactId, $delivery->recipient, $delivery->subject, $delivery->body, $delivery->templateVersion), $delivery->reviewHash);
            }
        }

        return false;
    }
}
