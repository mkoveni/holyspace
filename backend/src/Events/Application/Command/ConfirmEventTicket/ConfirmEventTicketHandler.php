<?php

declare(strict_types=1);

namespace App\Events\Application\Command\ConfirmEventTicket;

use App\Events\Application\Command\ExpireEventPayments\ExpireEventPaymentsCommand;
use App\Events\Application\Command\ExpireEventPayments\ExpireEventPaymentsHandler;
use App\Events\Application\ReadModel\OccurrenceDirectory;
use App\Events\Domain\Repository\EventRegistrationRepository;
use App\Shared\Application\WorkflowTransaction;
use App\Shared\Domain\ValueObject\Input;
use DateTimeImmutable;
use DateTimeZone;
use OutOfBoundsException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class ConfirmEventTicketHandler
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
    public function __invoke(ConfirmEventTicketCommand $message): bool
    {
        $eventId = $message->eventId;
        $occurrenceId = $message->occurrenceId;
        $personId = $message->personId;
        $orderId = $message->orderId;

        return $this->transactions->run(function () use ($eventId, $occurrenceId, $personId, $orderId) {
            ($this->expireEventPaymentsHandler)(new ExpireEventPaymentsCommand($eventId, $occurrenceId));
            $record = $this->records->forPerson($occurrenceId, $personId);
            if (!$record || $record->paymentOrderId !== $orderId || !$this->available($this->occurrence($eventId, $occurrenceId))) {
                return false;
            }
            if ('confirmed' === $record->status) {
                return true;
            }
            if ('pending_payment' !== $record->status) {
                return false;
            }
            $this->records->save($record->transition('confirmed', 'Hosted ticket payment verified.'));

            return true;
        });
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
}
