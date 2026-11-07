<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Infrastructure;

use App\ChildrenMinistry\Messaging\Application\DeliveryPolicy;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\Communications\Application\Contract\DeliveryEligibility;
use App\Communications\Application\Contract\DeliverySnapshot;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\IdentityAccess\Application\Contract\ResponsibleAccountDirectory;
use DateTimeImmutable;

/**
 * Children Ministry's judgement of its own deliveries for Communications. Before each send the approver must still hold the
 * sending and safeguarding permissions, and the guardian, contact, verification and wording must be as reviewed.
 */
final readonly class GuardianDeliveryEligibility implements DeliveryEligibility
{
    public function __construct(
        private DeliveryPolicy $policy,
        private ResponsibleAccountDirectory $accounts,
        private PermissionChecker $authorization,
    ) {
    }

    public function sourceContext(): string
    {
        return 'children-ministry';
    }

    public function authorize(DeliverySnapshot $delivery, string $actor, string $operation): void
    {
        if ('receipt' === $operation) {
            $this->authorization->requireActorPermission($actor, 'children.communications.receipts');

            return;
        }
        $this->authorization->requireActorPermission($actor, 'children.communications.send');
        $this->authorization->requirePermission('children.safeguarding');
    }

    public function eligible(DeliverySnapshot $delivery, DateTimeImmutable $at): bool
    {
        if (!$this->accounts->eligible($delivery->approvedBy, 'children.communications.send') || !$this->accounts->eligible($delivery->approvedBy, 'children.safeguarding')) {
            return false;
        }
        try {
            $this->policy->recheck($delivery, $at);

            return true;
        } catch (ChildrenException) {
            return false;
        }
    }
}
