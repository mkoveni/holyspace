<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\ApproveVolunteerReminder;

use App\Communications\Application\Contract\DeliverySnapshot;
use App\Communications\Application\Contract\EmailDeliveries;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use App\Volunteering\Application\Contract\ReminderReader;
use App\Volunteering\Application\Contract\ServingAudit;
use App\Volunteering\Application\ReminderReview;
use App\Volunteering\Application\RosterPlanning;
use App\Volunteering\Application\ServingPermissions;
use App\Volunteering\Domain\Repository\ReminderRepository;
use App\Volunteering\Domain\ServingRules;
use App\Volunteering\Domain\VolunteerException;
use App\Volunteering\Domain\VolunteerReminder;
use Psr\Clock\ClockInterface;

/**
 * Java `ApproveVolunteerReminderHandler`: re-renders the reminder under the volunteer, shift and assignment locks
 * and queues it only when the hash still matches the review. The rendered HTML is frozen into the delivery;
 * Volunteering is asked again before every send (`VolunteerReminderEligibility`). A retried request returns the
 * original delivery.
 */
final readonly class ApproveVolunteerReminderHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private RosterPlanning $planning,
        private ReminderReader $reminders,
        private ReminderRepository $repository,
        private ReminderReview $review,
        private EmailDeliveries $emails,
        private ServingAudit $audit,
        private IdGenerator $ids,
        private ClockInterface $clock,
    ) {
    }

    public function handle(ApproveVolunteerReminderCommand $command): string
    {
        $this->auth->requireActorPermission($command->actorId, ServingPermissions::COMMUNICATIONS);
        $evidence = ServingRules::text($command->verificationEvidence, 500);
        $requestId = ServingRules::id($command->requestId);
        $locked = $this->planning->lock($command->assignmentId);
        $previous = $this->reminders->reminder($requestId);
        if (null !== $previous) {
            if ($previous->assignmentId !== $command->assignmentId || $previous->contactId !== $command->contactId || $previous->createdBy !== $command->actorId
                || $previous->reviewHash !== $command->reviewHash || $previous->evidence !== $evidence) {
                throw VolunteerException::conflict('This reminder request was already used.');
            }

            return $previous->deliveryId;
        }
        $now = $this->clock->now();
        $snapshot = $this->review->review($locked->assignment->id, $command->contactId, $command->message, $now);
        if ($snapshot->hash !== $command->reviewHash) {
            throw VolunteerException::conflict('The shift, contact or template changed. Review the reminder again.');
        }
        $deliveryId = $this->ids->uuid();
        $this->repository->insert(VolunteerReminder::approve($requestId, $locked->assignment->id, $snapshot->assignmentVersion, $snapshot->shiftRevision, $command->contactId, $snapshot->hash, $evidence, $command->actorId, $now, $deliveryId));
        $this->emails->queue('volunteering', new DeliverySnapshot(
            $deliveryId,
            $requestId,
            null,
            $command->contactId,
            $snapshot->recipient,
            $snapshot->subject,
            $snapshot->html,
            $snapshot->hash,
            $command->actorId,
            $now,
            $now,
            'QUEUED',
            0,
            '<'.$deliveryId.'@onechurch.delivery>',
            null,
            null,
            null,
            '',
            false,
            0,
            'text/html',
            ReminderReview::TEMPLATE,
            $snapshot->templateVersion,
        ));
        $this->audit->record($command->actorId, 'reminder.approved', $requestId, $now);

        return $deliveryId;
    }
}
