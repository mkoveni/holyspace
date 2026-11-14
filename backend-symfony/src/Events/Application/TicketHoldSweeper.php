<?php

declare(strict_types=1);

namespace App\Events\Application;

use App\Events\Domain\Repository\TicketRepository;
use App\Shared\Application\UnitOfWork;
use Psr\Clock\ClockInterface;

/** Marks lapsed holds EXPIRED so their seats are free for others. A payment that still arrives is flagged for refund. */
final readonly class TicketHoldSweeper
{
    public function __construct(private TicketRepository $tickets, private UnitOfWork $transactions, private ClockInterface $clock)
    {
    }

    /** @return int how many holds expired */
    public function sweep(int $limit = 200): int
    {
        $expired = 0;
        foreach ($this->tickets->lapsed($this->clock->now(), $limit) as $id) {
            $expired += $this->transactions->execute(function () use ($id): int {
                $order = $this->tickets->find($id);
                if (null === $order) {
                    return 0;
                }
                $this->tickets->lockOccurrence($order->occurrenceId);
                $locked = $this->tickets->lock($id);
                $now = $this->clock->now();
                if (null === $locked || 'RESERVED' !== $locked->status || $locked->expiresAt > $now) {
                    return 0;
                }
                $this->tickets->setStatus($id, 'EXPIRED');
                $this->tickets->audit($id, 'TICKET_HOLD_EXPIRED', 'Hold lapsed without payment.', $now);

                return 1;
            });
        }

        return $expired;
    }
}
