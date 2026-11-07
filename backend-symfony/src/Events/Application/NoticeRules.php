<?php

declare(strict_types=1);

namespace App\Events\Application;

use App\Events\Application\Contract\EventView;
use App\Events\Application\Contract\OccurrenceView;
use App\Events\Application\Contract\RegistrationView;
use App\Events\Domain\EventException;
use DateTimeImmutable;

/** When a notice may be sent, which template carries it, and the hash that freezes what was reviewed. */
final class NoticeRules
{
    private const array PURPOSES = ['CONFIRMATION', 'REMINDER', 'CANCELLATION', 'RESCHEDULED'];
    private const int CANCELLATION_DAYS = 30;

    private function __construct()
    {
    }

    public static function template(?string $purpose): string
    {
        if (!\in_array($purpose, self::PURPOSES, true)) {
            throw EventException::invalid('Choose a confirmation, reminder, cancellation or rescheduling notice.');
        }

        return 'events.'.strtolower($purpose);
    }

    public static function allowed(RegistrationView $booking, OccurrenceView $occurrence, EventView $event, string $purpose, DateTimeImmutable $now): bool
    {
        if ('CANCELLATION' === $purpose) {
            return 'CANCELLED' === $booking->status && $now < $occurrence->endsAt->modify('+'.(self::CANCELLATION_DAYS * 86400).' seconds');
        }

        return $event->active && 'CONFIRMED' === $booking->status && 'SCHEDULED' === $occurrence->status
            && $occurrence->startsAt > $now
            && ('RESCHEDULED' !== $purpose || $occurrence->scheduleRevision > 0);
    }

    public static function hash(RegistrationView $booking, OccurrenceView $occurrence, int $eventVersion, string $purpose, string $contactId, string $recipient, string $subject, string $html, int $templateVersion): string
    {
        return Fingerprint::of($booking->id, $booking->version, $occurrence->scheduleRevision, $eventVersion, $purpose, $contactId, $recipient, $subject, $html, $templateVersion);
    }
}
