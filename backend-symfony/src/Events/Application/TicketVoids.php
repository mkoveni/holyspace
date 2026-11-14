<?php

declare(strict_types=1);

namespace App\Events\Application;

use App\Events\Application\Contract\EventJournal;
use App\Events\Domain\EventException;
use App\Events\Domain\Repository\TicketRepository;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\UnitOfWork;
use Psr\Clock\ClockInterface;

/**
 * Staff choose which tickets a partial refund covers (design 5.8). Voiding never moves money: the refund is made in the
 * Yoco dashboard. When the voided tickets add up to a refund that is waiting, Events matches and credits it.
 */
final readonly class TicketVoids
{
    public function __construct(
        private TicketRepository $tickets,
        private TicketOrderSettler $settler,
        private PermissionChecker $permissions,
        private EventJournal $journal,
        private UnitOfWork $transactions,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param list<string> $ticketIds
     *
     * @return array{matched: bool, refundWaitingMinor: int}
     */
    public function void(string $actorId, string $occurrenceId, string $orderId, array $ticketIds): array
    {
        $this->permissions->requireActorPermission($actorId, 'events.tickets.manage');
        if ([] === $ticketIds || \count($ticketIds) > 50) {
            throw EventException::invalid('Choose the tickets to void.');
        }

        return $this->transactions->execute(function () use ($actorId, $occurrenceId, $orderId, $ticketIds): array {
            $order = $this->tickets->find($orderId);
            if (null === $order || $order->occurrenceId !== $occurrenceId) {
                throw EventException::missing('Ticket order');
            }
            $own = array_column($this->tickets->ticketPrices($orderId), 'id');
            if ([] !== array_diff($ticketIds, $own)) {
                throw EventException::invalid('Those tickets do not belong to this order.');
            }
            $matched = $this->settler->voided($orderId, array_values(array_unique($ticketIds)));
            $this->journal->audit($actorId, 'tickets.voided', $orderId, \count($ticketIds).' ticket(s) voided by staff.', $this->clock->now());

            return ['matched' => $matched, 'refundWaitingMinor' => $this->tickets->unmatchedRefund($orderId)];
        });
    }
}
