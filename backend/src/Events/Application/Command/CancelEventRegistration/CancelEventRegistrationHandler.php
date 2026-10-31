<?php

declare(strict_types=1);

namespace App\Events\Application\Command\CancelEventRegistration;

use App\Events\Application\ReadModel\OccurrenceDirectory;
use App\Events\Domain\Repository\EventRegistrationRepository;
use App\Shared\Application\WorkflowTransaction;
use App\Shared\Domain\ValueObject\Input;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use OutOfBoundsException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class CancelEventRegistrationHandler
{
    public function __construct(
        private EventRegistrationRepository $records,
        private OccurrenceDirectory $occurrences,
        private WorkflowTransaction $transactions,
        private ClockInterface $clock,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(CancelEventRegistrationCommand $message): object
    {
        $eventId = $message->eventId;
        $occurrenceId = $message->occurrenceId;
        $id = $message->id;
        $reason = $message->reason;
        $expectedVersion = $message->expectedVersion;

        return $this->transactions->run(function () use ($eventId, $occurrenceId, $id, $reason, $expectedVersion) {
            $occurrence = $this->occurrence($eventId, $occurrenceId);
            Input::id($id);
            Input::text($reason, 1000);
            $registration = $this->records->find($id) ?? throw new OutOfBoundsException('Registration not found.');
            if ($registration->occurrenceId !== $occurrenceId) {
                throw new OutOfBoundsException('Registration not found.');
            }
            $this->version($registration->version, $expectedVersion);
            if ('cancelled' !== $registration->status) {
                $this->records->save($registration->transition('cancelled', $reason));
                if ($this->available($occurrence)) {
                    $this->promote($occurrenceId);
                }
            }

            return $this->records->find($id);
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

    private function version(int $actual, int $expected): void
    {
        if ($actual !== $expected) {
            throw new DomainException('This record changed. Reload and retry.');
        }
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
