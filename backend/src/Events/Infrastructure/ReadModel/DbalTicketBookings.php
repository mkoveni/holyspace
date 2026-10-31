<?php

declare(strict_types=1);

namespace App\Events\Infrastructure\ReadModel;

use App\Events\Application\Command\CancelEventRegistration\CancelEventRegistrationCommand;
use App\Events\Application\Command\CancelEventRegistration\CancelEventRegistrationHandler;
use App\Events\Application\Command\ConfirmEventTicket\ConfirmEventTicketCommand;
use App\Events\Application\Command\ConfirmEventTicket\ConfirmEventTicketHandler;
use App\Events\Application\Command\ExpireEventPayments\ExpireEventPaymentsCommand;
use App\Events\Application\Command\ExpireEventPayments\ExpireEventPaymentsHandler;
use App\Events\Application\Command\ReserveEventTicket\ReserveEventTicketCommand;
use App\Events\Application\Command\ReserveEventTicket\ReserveEventTicketHandler;
use App\Events\Application\Port\TicketReservations;
use App\Events\Application\ReadModel\OccurrenceDirectory;
use App\Events\Application\ReadModel\TicketBookings;
use App\Events\Domain\Model\RegistrationSettings;
use App\Events\Domain\Repository\EventRegistrationRepository;
use App\Shared\Application\WorkflowTransaction;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use DomainException;
use OutOfBoundsException;
use Symfony\Component\Clock\Clock;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(TicketBookings::class)]
#[AsAlias(TicketReservations::class)]
final readonly class DbalTicketBookings implements TicketReservations
{
    public function __construct(
        private Connection $db,
        private EventRegistrationRepository $records,
        private OccurrenceDirectory $occurrences,
        private WorkflowTransaction $transactions,
        private ReserveEventTicketHandler $reserveEventTicketHandler,
        private ConfirmEventTicketHandler $confirmEventTicketHandler,
        private CancelEventRegistrationHandler $cancelEventRegistrationHandler,
        private ExpireEventPaymentsHandler $expireEventPaymentsHandler,
    ) {
    }

    public function offers(): array
    {
        $items = [];
        $settingsRows = $this->db->fetchAllAssociative('SELECT id, payload FROM event_registration_settings');
        $occurrences = $this->occurrences->getMany(array_column($settingsRows, 'id'));
        $reserved = [];
        foreach ($this->db->fetchAllAssociative("SELECT occurrence_id, COUNT(*) AS total FROM event_registrations WHERE status IN ('confirmed', 'pending_payment') GROUP BY occurrence_id") as $row) {
            $reserved[$row['occurrence_id']] = (int) $row['total'];
        }
        foreach ($settingsRows as $row) {
            $id = $row['id'];
            $settings = new RegistrationSettings(...json_decode($row['payload'], true, flags: JSON_THROW_ON_ERROR));
            $occurrence = $occurrences[$id] ?? null;
            if (null === $settings->ticketPriceMinor || !$settings->open || !$occurrence || 'cancelled' === $occurrence['status'] || new DateTimeImmutable($occurrence['endsAt']) <= Clock::get()->now()) {
                continue;
            }
            $items[] = ['id' => $id, 'title' => $occurrence['title'], 'startsAt' => $occurrence['startsAt'], 'location' => $occurrence['location'], 'amountMinor' => $settings->ticketPriceMinor, 'currency' => 'ZAR', 'remaining' => null === $settings->capacity ? null : max(0, $settings->capacity - ($reserved[$id] ?? 0))];
        }
        usort($items, static fn ($a, $b) => strcmp($a['startsAt'], $b['startsAt']));

        return $items;
    }

    public function price(string $occurrenceId): int
    {
        return $this->records->settings($occurrenceId)->ticketPriceMinor ?? throw new DomainException('This occurrence does not offer paid tickets.');
    }

    private function eventId(string $id): string
    {
        return ($this->occurrences->get($id) ?? throw new OutOfBoundsException('Occurrence not found.'))['eventId'];
    }

    public function reserve(string $occurrenceId, string $personId, string $orderId, string $expiresAt): void
    {
        ($this->reserveEventTicketHandler)(new ReserveEventTicketCommand($this->eventId($occurrenceId), $occurrenceId, $personId, $orderId, $expiresAt));
    }

    public function confirm(string $occurrenceId, string $personId, string $orderId): bool
    {
        return ($this->confirmEventTicketHandler)(new ConfirmEventTicketCommand($this->eventId($occurrenceId), $occurrenceId, $personId, $orderId));
    }

    public function release(string $occurrenceId, string $personId, string $orderId): void
    {
        $record = $this->records->forPerson($occurrenceId, $personId);
        if ($record && $record->paymentOrderId === $orderId && 'cancelled' !== $record->status) {
            ($this->cancelEventRegistrationHandler)(new CancelEventRegistrationCommand($this->eventId($occurrenceId), $occurrenceId, $record->id, 'Payment was unsuccessful or refunded.', $record->version));
        }
    }

    public function status(string $occurrenceId, string $personId, string $orderId): ?string
    {
        $record = $this->records->forPerson($occurrenceId, $personId);

        return $record?->paymentOrderId === $orderId ? $record->status : null;
    }

    public function expire(): void
    {
        $this->transactions->run(function () {
            foreach ($this->db->fetchFirstColumn("SELECT DISTINCT occurrence_id FROM event_registrations WHERE status='pending_payment'") as $id) {
                ($this->expireEventPaymentsHandler)(new ExpireEventPaymentsCommand($this->eventId($id), $id));
            }
        });
    }
}
