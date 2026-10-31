<?php

declare(strict_types=1);

namespace App\Events\Application\Command\ExpireEventPayments;

use App\Events\Application\ReadModel\OccurrenceDirectory;
use App\Events\Domain\Repository\EventRegistrationRepository;
use App\Shared\Domain\ValueObject\Input;
use DateTimeImmutable;
use DateTimeZone;
use OutOfBoundsException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class ExpireEventPaymentsHandler
{
    public function __construct(
        private EventRegistrationRepository $records,
        private OccurrenceDirectory $occurrences,
        private ClockInterface $clock,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(ExpireEventPaymentsCommand $message): void
    {
        $eventId = $message->eventId;
        $occurrenceId = $message->occurrenceId;
        $occurrence = $this->occurrence($eventId, $occurrenceId);
        foreach ($this->records->pendingPayments($occurrenceId) as $record) {
            if (!$this->available($occurrence) || new DateTimeImmutable($record->paymentExpiresAt) <= $this->clock->now()) {
                $this->records->save($record->transition('cancelled', 'Ticket payment reservation expired or event became unavailable.'));
            }
        }
        if ($this->available($occurrence)) {
            $this->promote($occurrenceId);
        }
    }

    private function occurrence(string $eventId, string $id): array
    {
        Input::id($eventId);
        Input::id($id);
        $occurrence = $this->occurrences->get($id);
        if (!$occurrence || $occurrence['eventId'] !== $eventId) {
            throw new OutOfBoundsException('Occurrence not found for this event.');
        }

        return $occurrence;
    }

    private function available(array $occurrence): bool
    {
        return 'cancelled' !== $occurrence['status'] && new DateTimeImmutable($occurrence['endsAt'], new DateTimeZone('UTC')) > $this->clock->now();
    }

    private function promote(string $occurrenceId): void
    {
        $settings = $this->records->settings($occurrenceId);
        if (null !== $settings->ticketPriceMinor) {
            return;
        }
        $counts = $this->records->counts($occurrenceId);
        $confirmed = $counts['confirmed'] + $counts['pending_payment'];
        while ((null === $settings->capacity || $confirmed < $settings->capacity) && $next = $this->records->nextWaitlisted($occurrenceId)) {
            $this->records->save($next->transition('confirmed', 'A place became available; promoted from the waitlist.'));
            ++$confirmed;
        }
    }
}
