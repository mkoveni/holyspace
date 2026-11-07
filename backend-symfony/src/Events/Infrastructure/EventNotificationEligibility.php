<?php

declare(strict_types=1);

namespace App\Events\Infrastructure;

use App\Communications\Application\Contract\DeliveryEligibility;
use App\Communications\Application\Contract\DeliverySnapshot;
use App\Events\Application\Contract\EventNotificationReader;
use App\Events\Application\Contract\EventReader;
use App\Events\Application\Contract\OccurrenceReader;
use App\Events\Application\Contract\RegistrationReader;
use App\Events\Application\NoticeRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\IdentityAccess\Application\Contract\ResponsibleAccountDirectory;
use App\People\Application\Contract\PersonDirectory;
use App\People\Application\Contract\PersonEmailDirectory;
use DateTimeImmutable;

/**
 * Events' judgement of its own deliveries for Communications. Before each send the approver must still hold the
 * sending permission and the booking, occurrence, event, person and email must be exactly as reviewed.
 */
final readonly class EventNotificationEligibility implements DeliveryEligibility
{
    public function __construct(
        private PermissionChecker $authorization,
        private ResponsibleAccountDirectory $accounts,
        private EventNotificationReader $notifications,
        private RegistrationReader $registrations,
        private OccurrenceReader $occurrences,
        private EventReader $events,
        private PersonDirectory $people,
        private PersonEmailDirectory $emails,
    ) {
    }

    public function sourceContext(): string
    {
        return 'events';
    }

    public function authorize(DeliverySnapshot $delivery, string $actor, string $operation): void
    {
        $this->authorization->requireActorPermission($actor, 'events.communications.send');
    }

    public function eligible(DeliverySnapshot $delivery, DateTimeImmutable $at): bool
    {
        if (!$this->accounts->eligible($delivery->approvedBy, 'events.communications.send') || null === $delivery->templateVersion) {
            return false;
        }
        $notice = $this->notifications->notification($delivery->communicationId);
        if (null === $notice || $notice->deliveryId !== $delivery->id || $notice->createdBy !== $delivery->approvedBy
            || $notice->contactId !== $delivery->contactId || $notice->reviewHash !== $delivery->reviewHash
            || NoticeRules::template($notice->purpose) !== $delivery->templateKey) {
            return false;
        }
        $booking = $this->registrations->find($notice->registrationId);
        $occurrence = null === $booking ? null : $this->occurrences->find($booking->occurrenceId);
        $event = null === $occurrence ? null : $this->events->find($occurrence->eventId);
        if (null === $booking || null === $occurrence || null === $event) {
            return false;
        }
        if ($booking->version !== $notice->registrationVersion || $occurrence->scheduleRevision !== $notice->scheduleRevision
            || $event->version !== $notice->eventVersion || !NoticeRules::allowed($booking, $occurrence, $event, $notice->purpose, $at)
            || !($this->people->get($booking->personId)->available ?? false)) {
            return false;
        }
        foreach ($this->emails->active($booking->personId) as $email) {
            if ($email->id === $notice->contactId && $email->value === $delivery->recipient) {
                return NoticeRules::hash($booking, $occurrence, $event->version, $notice->purpose, $notice->contactId, $delivery->recipient, $delivery->subject, $delivery->body, $delivery->templateVersion) === $delivery->reviewHash;
            }
        }

        return false;
    }
}
