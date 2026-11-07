<?php

declare(strict_types=1);

namespace App\Events\Application;

use App\Communications\Application\Contract\DeliverySnapshot;
use App\Communications\Application\Contract\EmailDeliveries;
use App\Events\Application\Contract\EventJournal;
use App\Events\Application\Contract\EventNotificationReader;
use App\Events\Application\Contract\RegistrationReader;
use App\Events\Domain\EventException;
use App\Events\Domain\EventNotification;
use App\Events\Domain\EventRules;
use App\Events\Domain\Repository\EventNotificationRepository;
use App\Events\Domain\Repository\OccurrenceRepository;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

/**
 * Approves a reviewed notice. The booking's person is locked before its occurrence; the content approved is
 * frozen into the delivery, and Communications asks Events again before every send.
 */
final readonly class EventMessageApproval
{
    public function __construct(
        private EventMessageReview $review,
        private EventNotificationReader $reader,
        private EventNotificationRepository $notifications,
        private RegistrationReader $registrations,
        private OccurrenceRepository $occurrences,
        private PersonDirectory $people,
        private EmailDeliveries $emails,
        private EventJournal $journal,
        private IdGenerator $ids,
        private ClockInterface $clock,
    ) {
    }

    /** @return string the delivery id (the original one when the request is repeated) */
    public function approve(string $actorId, string $requestId, string $registrationId, string $contactId, ?string $purpose, string $message, ?string $reviewHash, string $verification): string
    {
        EventRules::id($requestId);
        $evidence = EventRules::text($verification, 500);
        $requestHash = Fingerprint::of($actorId, $registrationId, $contactId, $purpose, $message, $reviewHash, $evidence);

        $booking = $this->registrations->find($registrationId) ?? throw EventException::missing('Registration');
        $this->people->lock($booking->personId) ?? throw EventException::missing('Person');
        $this->occurrences->findForUpdate($booking->occurrenceId) ?? throw EventException::missing('Occurrence');
        $previous = $this->reader->notification($requestId);
        if (null !== $previous) {
            if ($previous->requestHash !== $requestHash) {
                throw EventException::conflict('This notification request was already used.');
            }

            return $previous->deliveryId;
        }
        $now = $this->clock->now();
        $snapshot = $this->review->review($registrationId, $contactId, $purpose, $message, $now);
        if ($snapshot->hash !== $reviewHash) {
            throw EventException::conflict('The booking, occurrence, email or template changed. Review the notice again.');
        }
        $delivery = $this->ids->uuid();
        $this->notifications->insert(new EventNotification($requestId, $registrationId, $snapshot->registrationVersion, $snapshot->scheduleRevision, $snapshot->eventVersion, (string) $purpose, $contactId, (string) $reviewHash, $requestHash, $evidence, $actorId, $now, $delivery));
        $this->emails->queue('events', new DeliverySnapshot(
            $delivery,
            $requestId,
            null,
            $contactId,
            $snapshot->recipient,
            $snapshot->subject,
            $snapshot->html,
            (string) $reviewHash,
            $actorId,
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
            NoticeRules::template($purpose),
            $snapshot->templateVersion,
        ));
        $this->journal->audit($actorId, 'notification.approved', $requestId, '', $now);

        return $delivery;
    }
}
