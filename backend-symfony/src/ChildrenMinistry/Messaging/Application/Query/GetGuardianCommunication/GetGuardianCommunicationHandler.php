<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application\Query\GetGuardianCommunication;

use App\ChildrenMinistry\Messaging\Application\CommunicationView;
use App\ChildrenMinistry\Messaging\Application\Contract\GuardianCommunicationReader;
use App\ChildrenMinistry\Messaging\Application\GuardianCommunicationPolicy;
use App\ChildrenMinistry\Messaging\Domain\CommunicationStatus;
use App\ChildrenMinistry\Messaging\Domain\CommunicationType;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use Psr\Clock\ClockInterface;

/** A draft's wording is only shown while its authority, policy and session still qualify. */
final readonly class GetGuardianCommunicationHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private GuardianCommunicationReader $reader,
        private GuardianCommunicationPolicy $policy,
        private ClockInterface $clock,
    ) {
    }

    public function handle(GetGuardianCommunicationQuery $query): CommunicationView
    {
        $this->authorization->requirePermission('children.safeguarding');
        $communication = $this->reader->find($query->communicationId) ?? throw ChildrenException::missing('Communication');
        if (CommunicationStatus::Draft === $communication->status) {
            $now = $this->clock->now();
            $this->policy->guardian($communication->childId, $communication->authorityId, $now);
            $programme = $this->policy->programme($communication->programmeId, $communication->childId, $communication->type->value, $communication->sessionId, $now);
            if (CommunicationType::ConsentRenewal === $communication->type && $communication->policyId !== $programme->currentPolicyId) {
                throw ChildrenException::conflict('The consent policy changed. Cancel this draft and prepare a current request.');
            }
        }

        return $communication;
    }
}
