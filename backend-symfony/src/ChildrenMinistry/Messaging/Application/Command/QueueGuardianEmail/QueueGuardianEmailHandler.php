<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application\Command\QueueGuardianEmail;

use App\ChildrenMinistry\Messaging\Application\DeliveryPolicy;
use App\ChildrenMinistry\Messaging\Domain\Repository\GuardianCommunicationRepository;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\Communications\Application\Contract\DeliveryReader;
use App\Communications\Application\Contract\DeliverySnapshot;
use App\Communications\Application\Contract\EmailDeliveries;
use App\Communications\Application\Contract\EmailTransport;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

/**
 * Approves one reviewed message for one verified recipient, now or within 90 days. The approval is the fingerprint of the
 * exact wording and address the reviewer saw; one delivery per communication prevents duplicates. Sending itself, and a
 * fresh eligibility check, happen later in Communications.
 */
final readonly class QueueGuardianEmailHandler implements CommandHandler
{
    private const int FUTURE_DAYS = 90;

    public function __construct(
        private PermissionChecker $authorization,
        private DeliveryPolicy $policy,
        private GuardianCommunicationRepository $communications,
        private DeliveryReader $deliveries,
        private EmailDeliveries $queue,
        private EmailTransport $transport,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
        private IdGenerator $ids,
    ) {
    }

    /** @return string the delivery id */
    public function handle(QueueGuardianEmailCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.communications.send');
        $this->authorization->requirePermission('children.safeguarding');
        if (!$this->transport->configuration()->enabled) {
            throw ChildrenException::conflict('Email delivery is not configured.');
        }

        $communication = $this->communications->lock($command->communicationId) ?? throw ChildrenException::missing('Communication');
        $now = $this->clock->now();
        ChildrenRules::version($communication->version, $command->expectedVersion);
        $current = $this->policy->current($command->communicationId, $now);
        $verified = $this->policy->verified($command->verificationId, $current, $now);
        $rendered = $this->policy->render($current);
        if ($this->policy->hash($current, $verified, $rendered) !== $command->reviewHash) {
            throw ChildrenException::conflict('Review the current message and recipient before sending.');
        }
        if (null !== $this->deliveries->forCommunication($command->communicationId)) {
            throw ChildrenException::conflict('This communication already has a delivery record. Review that record before any further action.');
        }
        $at = $command->scheduledAt ?? $now;
        if ($at < $now->modify('-60 seconds') || $at > $now->modify('+'.self::FUTURE_DAYS.' days')) {
            throw ChildrenException::invalid('Schedule within the next 90 days.');
        }
        $this->policy->schedule($current, $at);
        if (!$verified->valid($at)) {
            throw ChildrenException::conflict('Email verification must cover the scheduled send time.');
        }

        $id = $this->ids->uuid();
        $this->queue->queue('children-ministry', new DeliverySnapshot(
            $id,
            $current->id,
            $verified->id,
            $verified->contactId,
            $verified->email,
            $rendered->subject,
            $rendered->html,
            $command->reviewHash,
            $command->actorId,
            $now,
            $at,
            $at > $now ? 'SCHEDULED' : 'QUEUED',
            0,
            '<'.$id.'@onechurch.delivery>',
            null,
            null,
            null,
            '',
            false,
            0,
            'text/html',
            $rendered->key,
            $rendered->version,
        ));
        $this->audit->record($command->actorId, 'guardian.email.queued', $id, '', $now);

        return $id;
    }
}
