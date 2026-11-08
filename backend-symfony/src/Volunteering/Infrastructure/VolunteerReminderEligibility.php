<?php

declare(strict_types=1);

namespace App\Volunteering\Infrastructure;

use App\Communications\Application\Contract\DeliveryEligibility;
use App\Communications\Application\Contract\DeliverySnapshot;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\IdentityAccess\Application\Contract\ResponsibleAccountDirectory;
use App\People\Application\Contract\PersonDirectory;
use App\People\Application\Contract\PersonEmailDirectory;
use App\Volunteering\Application\ActivityRegistry;
use App\Volunteering\Application\Contract\ReminderReader;
use App\Volunteering\Application\Contract\RosterReader;
use App\Volunteering\Application\Contract\VolunteerReader;
use App\Volunteering\Application\ReminderReview;
use App\Volunteering\Application\ServingPermissions;
use App\Volunteering\Domain\VolunteerException;
use DateTimeImmutable;

/**
 * Java `VolunteerReminderEligibility`: before each send the approver must still hold the sending permission, and the
 * assignment, shift, linked activity, volunteer, person and email must be exactly as reviewed.
 */
final readonly class VolunteerReminderEligibility implements DeliveryEligibility
{
    public function __construct(
        private PermissionChecker $auth,
        private ResponsibleAccountDirectory $accounts,
        private ReminderReader $reminders,
        private RosterReader $roster,
        private VolunteerReader $volunteers,
        private PersonEmailDirectory $emails,
        private PersonDirectory $people,
        private ActivityRegistry $activities,
    ) {
    }

    public function sourceContext(): string
    {
        return 'volunteering';
    }

    public function authorize(DeliverySnapshot $delivery, string $actor, string $operation): void
    {
        $this->auth->requireActorPermission($actor, ServingPermissions::COMMUNICATIONS);
    }

    public function eligible(DeliverySnapshot $delivery, DateTimeImmutable $at): bool
    {
        if (!$this->accounts->eligible($delivery->approvedBy, ServingPermissions::COMMUNICATIONS) || ReminderReview::TEMPLATE !== $delivery->templateKey || null === $delivery->templateVersion) {
            return false;
        }
        $reminder = $this->reminders->reminder($delivery->communicationId);
        if (null === $reminder || $reminder->deliveryId !== $delivery->id || $reminder->createdBy !== $delivery->approvedBy
            || $reminder->contactId !== $delivery->contactId || $reminder->reviewHash !== $delivery->reviewHash) {
            return false;
        }
        $assignment = $this->roster->assignment($reminder->assignmentId);
        $shift = null === $assignment ? null : $this->roster->shift($assignment->shiftId);
        $volunteer = null === $assignment ? null : $this->volunteers->volunteer($assignment->volunteerId);
        if (null === $assignment || null === $shift || null === $volunteer) {
            return false;
        }
        try {
            $this->activities->require($shift);
        } catch (VolunteerException) {
            return false;
        }
        if ('CONFIRMED' !== $assignment->status || 'OPEN' !== $shift->status || !($shift->startsAt > $at) || 'ACTIVE' !== $volunteer->status
            || true !== $this->people->get($volunteer->personId)?->available
            || $reminder->assignmentVersion !== $assignment->version || $reminder->shiftVersion !== $shift->scheduleRevision) {
            return false;
        }
        foreach ($this->emails->active($volunteer->personId) as $email) {
            if ($email->id === $reminder->contactId && $email->value === $delivery->recipient) {
                return ReminderReview::hash($assignment->id, $assignment->version, $shift->scheduleRevision, $reminder->contactId, $delivery->recipient, $delivery->subject, $delivery->body, $delivery->templateVersion) === $delivery->reviewHash;
            }
        }

        return false;
    }
}
