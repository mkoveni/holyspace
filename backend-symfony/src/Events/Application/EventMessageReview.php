<?php

declare(strict_types=1);

namespace App\Events\Application;

use App\Communications\Application\Contract\EmailTemplates;
use App\Events\Application\Contract\EventReader;
use App\Events\Application\Contract\OccurrenceReader;
use App\Events\Application\Contract\RegistrationReader;
use App\Events\Application\Contract\ReviewedNotice;
use App\Events\Domain\EventException;
use App\Events\Domain\EventRules;
use App\People\Application\Contract\PersonDirectory;
use App\People\Application\Contract\PersonEmail;
use App\People\Application\Contract\PersonEmailDirectory;
use DateTimeImmutable;

/** Renders a notice for one booking and one of the person's current email addresses, without sending anything. */
final readonly class EventMessageReview
{
    public function __construct(
        private RegistrationReader $registrations,
        private OccurrenceReader $occurrences,
        private EventReader $events,
        private PersonDirectory $people,
        private PersonEmailDirectory $emails,
        private EmailTemplates $templates,
    ) {
    }

    /** @return list<PersonEmail> */
    public function contacts(string $registrationId): array
    {
        $booking = $this->registrations->find($registrationId) ?? throw EventException::missing('Registration');

        return $this->emails->active($booking->personId);
    }

    public function review(string $registrationId, string $contactId, ?string $purpose, string $message, DateTimeImmutable $now): ReviewedNotice
    {
        $key = NoticeRules::template($purpose);
        $note = EventRules::text($message, 2000);
        $booking = $this->registrations->find($registrationId) ?? throw EventException::missing('Registration');
        $occurrence = $this->occurrences->find($booking->occurrenceId) ?? throw EventException::missing('Occurrence');
        $event = $this->events->find($occurrence->eventId) ?? throw EventException::missing('Event');
        $person = $this->people->get($booking->personId);
        if (null === $person || !$person->available) {
            throw EventException::conflict('The person is unavailable.');
        }
        if (!NoticeRules::allowed($booking, $occurrence, $event, (string) $purpose, $now)) {
            throw EventException::conflict('This notice does not match the current booking or occurrence. Confirmations, reminders and changes require a confirmed upcoming booking; cancellations require a cancelled booking within 30 days of the occurrence.');
        }
        $recipient = null;
        foreach ($this->emails->active($booking->personId) as $email) {
            if ($email->id === $contactId) {
                $recipient = $email->value;
                break;
            }
        }
        if (null === $recipient) {
            throw EventException::conflict('Choose a current email address.');
        }
        $zone = EventRules::zone($occurrence->timeZone);
        $content = $this->templates->render($key, [
            'name' => $person->name,
            'event' => $event->title,
            'startsAt' => $occurrence->startsAt->setTimezone($zone)->format('D, j M Y H:i'),
            'endsAt' => $occurrence->endsAt->setTimezone($zone)->format('D, j M Y H:i'),
            'timeZone' => $occurrence->timeZone,
            'venue' => $occurrence->venueName,
            'message' => $note,
        ]);
        $hash = NoticeRules::hash($booking, $occurrence, $event->version, (string) $purpose, $contactId, $recipient, $content->subject, $content->html, $content->version);

        return new ReviewedNotice($booking->id, $booking->version, $occurrence->scheduleRevision, $event->version, (string) $purpose, $contactId, $recipient, $content->subject, $content->html, $hash, $content->version);
    }
}
