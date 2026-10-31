<?php

declare(strict_types=1);

namespace App\Events\Application\Command\ConfigureTicketPrice;

use App\Events\Application\Command\ExpireEventPayments\ExpireEventPaymentsCommand;
use App\Events\Application\Command\ExpireEventPayments\ExpireEventPaymentsHandler;
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

final readonly class ConfigureTicketPriceHandler
{
    public function __construct(
        private EventRegistrationRepository $records,
        private OccurrenceDirectory $occurrences,
        private WorkflowTransaction $transactions,
        private ClockInterface $clock,
        private ExpireEventPaymentsHandler $expireEventPaymentsHandler,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(ConfigureTicketPriceCommand $message): object
    {
        $eventId = $message->eventId;
        $occurrenceId = $message->occurrenceId;
        $amount = $message->amount;
        $version = $message->version;

        return $this->transactions->run(function () use ($eventId, $occurrenceId, $amount, $version) {
            ($this->expireEventPaymentsHandler)(new ExpireEventPaymentsCommand($eventId, $occurrenceId));
            $settings = $this->records->settings($occurrenceId);
            $this->version($settings->version, $version);
            if ($this->records->counts($occurrenceId)['pending_payment'] > 0) {
                throw new DomainException('Wait for reserved payments to complete or expire before changing the ticket price.');
            }
            $this->records->saveSettings($settings->ticketPrice($amount));
            if (null === $amount && $this->available($this->occurrence($eventId, $occurrenceId))) {
                $this->promote($occurrenceId);
            }

            return $this->records->settings($occurrenceId);
        });
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
