<?php

declare(strict_types=1);

namespace App\Events\Application\Command\ReserveEventTicket;

use App\Events\Application\Command\ExpireEventPayments\ExpireEventPaymentsCommand;
use App\Events\Application\Command\ExpireEventPayments\ExpireEventPaymentsHandler;
use App\Events\Application\ReadModel\OccurrenceDirectory;
use App\Events\Domain\Model\EventRegistration;
use App\Events\Domain\Repository\EventRegistrationRepository;
use App\People\Application\ReadModel\PersonDirectory;
use App\Shared\Application\WorkflowTransaction;
use App\Shared\Domain\ValueObject\Input;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class ReserveEventTicketHandler
{
    public function __construct(
        private EventRegistrationRepository $records,
        private OccurrenceDirectory $occurrences,
        private PersonDirectory $people,
        private WorkflowTransaction $transactions,
        private ClockInterface $clock,
        private ExpireEventPaymentsHandler $expireEventPaymentsHandler,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(ReserveEventTicketCommand $message): object
    {
        $eventId = $message->eventId;
        $occurrenceId = $message->occurrenceId;
        $personId = $message->personId;
        $orderId = $message->orderId;
        $expires = $message->expires;

        return $this->transactions->run(function () use ($eventId, $occurrenceId, $personId, $orderId, $expires) {
            ($this->expireEventPaymentsHandler)(new ExpireEventPaymentsCommand($eventId, $occurrenceId));
            $occurrence = $this->occurrence($eventId, $occurrenceId);
            $settings = $this->records->settings($occurrenceId);
            if (!$settings->open || !$this->available($occurrence) || null === $settings->ticketPriceMinor) {
                throw new DomainException('Paid registrations are closed for this occurrence.');
            }
            if (!$this->people->get($personId)) {
                throw new InvalidArgumentException('Person not found.');
            }
            $expiry = new DateTimeImmutable($expires);
            if ($expiry <= $this->clock->now() || $expiry > $this->clock->now()->modify('+20 minutes')) {
                throw new InvalidArgumentException('Invalid ticket reservation expiry.');
            }
            $previous = $this->records->forPerson($occurrenceId, $personId);
            if ($previous && 'cancelled' !== $previous->status) {
                throw new DomainException('You already have a registration or a reserved payment for this event.');
            }
            $counts = $this->records->counts($occurrenceId);
            if (null !== $settings->capacity && $counts['confirmed'] + $counts['pending_payment'] >= $settings->capacity) {
                throw new DomainException('This event has no available places. No payment was started.');
            }
            $record = $previous ? $previous->reserve($orderId, $expires) : new EventRegistration($occurrenceId, $personId, 'pending_payment', paymentOrderId: $orderId, paymentExpiresAt: $expires);
            $this->records->save($record);

            return $this->records->find($record->id);
        });
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
}
