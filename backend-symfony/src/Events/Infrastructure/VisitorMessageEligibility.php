<?php

declare(strict_types=1);

namespace App\Events\Infrastructure;

use App\Communications\Application\Contract\DeliverySnapshot;
use App\Events\Application\Contract\EventReader;
use App\Events\Application\Contract\OccurrenceReader;
use App\Events\Application\Contract\VisitMessagingReader;
use App\Events\Application\Contract\VisitorReader;
use App\Events\Application\Contract\VisitorRow;
use App\Events\Application\VisitorMessageRules;
use App\Events\Domain\VisitorCheckIn;
use App\IdentityAccess\Application\Contract\ResponsibleAccountDirectory;

/**
 * Events' judgement of an automatic visitor message before each send: staff still allow automatic messages for the
 * occurrence, the approver still holds the permission, the visitor was not removed, and nothing was changed after queueing.
 */
final readonly class VisitorMessageEligibility
{
    public function __construct(
        private VisitMessagingReader $messaging,
        private VisitorReader $visitors,
        private OccurrenceReader $occurrences,
        private EventReader $events,
        private ResponsibleAccountDirectory $accounts,
    ) {
    }

    public function eligible(DeliverySnapshot $delivery): bool
    {
        $purpose = VisitorMessageRules::purposeOf($delivery->templateKey);
        $message = $this->messaging->message($delivery->id);
        if (null === $purpose || null === $delivery->templateVersion || null === $message || $message['purpose'] !== $purpose
            || !$this->accounts->eligible($delivery->approvedBy, VisitorMessageRules::PERMISSION)) {
            return false;
        }
        $visit = $this->visitors->find($message['registrationId']);
        if (null === $visit || VisitorCheckIn::REMOVED === $visit->reviewStatus || null === $visit->email || $visit->email !== $delivery->recipient) {
            return false;
        }
        if (VisitorMessageRules::APP_INVITE === $purpose) {
            // Staff sent this one by hand: the approver's permission (checked above) is the only standing condition.
            return $this->unchanged($delivery, $visit, $purpose);
        }
        $occurrence = $this->occurrences->find($visit->occurrenceId);
        $event = null === $occurrence ? null : $this->events->find($occurrence->eventId);
        if (null === $occurrence || null === $event || !$event->active || 'SCHEDULED' !== $occurrence->status
            || $this->messaging->approver($occurrence->id) !== $delivery->approvedBy) {
            return false;
        }

        return $this->unchanged($delivery, $visit, $purpose);
    }

    private function unchanged(DeliverySnapshot $delivery, VisitorRow $visit, string $purpose): bool
    {
        $message = $this->messaging->message($delivery->id);
        $hash = VisitorMessageRules::hash($visit->registrationId, $purpose, $delivery->recipient, $delivery->subject, $delivery->body, $delivery->templateVersion ?? 0);

        return null !== $message && $hash === $message['reviewHash'] && $hash === $delivery->reviewHash;
    }
}
