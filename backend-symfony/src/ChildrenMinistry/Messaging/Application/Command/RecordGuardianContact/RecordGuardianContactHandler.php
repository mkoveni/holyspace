<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application\Command\RecordGuardianContact;

use App\ChildrenMinistry\Messaging\Application\GuardianCommunicationPolicy;
use App\ChildrenMinistry\Messaging\Domain\CommunicationType;
use App\ChildrenMinistry\Messaging\Domain\Repository\GuardianCommunicationRepository;
use App\ChildrenMinistry\Safeguarding\Domain\Repository\SafeguardingRepository;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\Communications\Application\Contract\DeliveryReader;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/**
 * Records that someone made contact by hand, or cancels the draft. Refused while an email delivery exists (other than a
 * cancelled one), so the same message cannot reach the guardian twice. Lock order: the communication, then the authority.
 */
final readonly class RecordGuardianContactHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private GuardianCommunicationPolicy $policy,
        private GuardianCommunicationRepository $repository,
        private SafeguardingRepository $safeguarding,
        private DeliveryReader $deliveries,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
    ) {
    }

    public function handle(RecordGuardianContactCommand $command): null
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.safeguarding');

        $current = $this->repository->lock($command->communicationId) ?? throw ChildrenException::missing('Communication');
        $delivery = $this->deliveries->forCommunication($current->id);
        if (null !== $delivery && 'CANCELLED' !== $delivery->status && $this->deliveries->belongsTo($delivery->id, 'children-ministry')) {
            throw ChildrenException::conflict('Review the existing email delivery before recording manual contact or cancelling this communication.');
        }
        $this->safeguarding->guardianAuthorityForUpdate($current->authorityId) ?? throw ChildrenException::missing('Guardian authority');
        $now = $this->clock->now();
        if ('CONTACTED' === $command->status) {
            $this->policy->guardian($current->childId, $current->authorityId, $now);
            $programme = $this->policy->programme($current->programmeId, $current->childId, $current->type->value, $current->sessionId, $now);
            if (CommunicationType::ConsentRenewal === $current->type && $current->policyId !== $programme->currentPolicyId) {
                throw ChildrenException::conflict('The consent policy changed. Cancel this draft and prepare a current request.');
            }
        }
        $changed = $current->finish($command->expectedVersion, $command->status, $command->reference, $command->actorId, $now);
        $this->repository->update($changed);
        $this->audit->record($command->actorId, 'guardian.communication.'.strtolower($changed->status->value), $changed->id, '', $now);

        return null;
    }
}
