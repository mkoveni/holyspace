<?php

declare(strict_types=1);

namespace App\Events\Application\Command\RegisterForEvent;

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

final readonly class RegisterForEventHandler
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
    public function __invoke(RegisterForEventCommand $message): object
    {
        $eventId = $message->eventId;
        $occurrenceId = $message->occurrenceId;
        $personId = $message->personId;

        return $this->transactions->run(function () use ($eventId, $occurrenceId, $personId) {
            ($this->expireEventPaymentsHandler)(new ExpireEventPaymentsCommand($eventId, $occurrenceId));
            $occurrence = $this->occurrence($eventId, $occurrenceId);
            Input::id($personId);
            if (!$this->people->get($personId)) {
                throw new InvalidArgumentException('Person not found.');
            }
            $settings = $this->records->settings($occurrenceId);
            if (null !== $settings->ticketPriceMinor) {
                throw new DomainException('This event requires hosted payment. Use the member ticket checkout.');
            }
            if (!$settings->open || !$this->available($occurrence)) {
                throw new DomainException('Registrations are closed for this occurrence.');
            }
            $previous = $this->records->forPerson($occurrenceId, $personId);
            if ($previous && 'cancelled' !== $previous->status) {
                throw new DomainException('This person is already registered.');
            }
            $this->promote($occurrenceId);
            $full = null !== $settings->capacity && $this->records->counts($occurrenceId)['confirmed'] >= $settings->capacity;
            if ($full && !$settings->waitlistEnabled) {
                throw new DomainException('This occurrence is full and the waitlist is disabled.');
            }
            $status = $full ? 'waitlisted' : 'confirmed';
            $registration = $previous ? $previous->transition($status, 'Registered again by staff.', true) : new EventRegistration($occurrenceId, $personId, $status);
            $this->records->save($registration);

            return $this->records->find($registration->id);
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
