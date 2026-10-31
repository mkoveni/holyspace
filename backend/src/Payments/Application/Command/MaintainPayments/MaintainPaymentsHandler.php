<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\MaintainPayments;

use App\Events\Application\Port\TicketReservations;
use App\Payments\Domain\Model\PaymentOrder;
use App\Payments\Domain\Repository\PaymentRepository;
use App\Shared\Application\WorkflowTransaction;
use DateTimeImmutable;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class MaintainPaymentsHandler
{
    public function __construct(
        private PaymentRepository $records,
        private TicketReservations $tickets,
        private WorkflowTransaction $transactions,
        private ClockInterface $clock,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(MaintainPaymentsCommand $message): void
    {
        $this->transactions->run(function () {
            $this->tickets->expire();
            foreach ($this->records->all('orders') as $order) {
                if (in_array($order->status, ['created', 'ready'], true) && new DateTimeImmutable($order->expiresAt) <= $this->clock->now()) {
                    if ($order->recurring) {
                        $this->review($order, 'Monthly giving setup expired without confirmation. Verify Payfast before closing this setup or creating another subscription.');
                    } else {
                        $this->records->save($order->change(['status' => 'expired']));
                    }
                } elseif ('live' === $order->mode && 'ticket' === $order->purpose && 'paid' === $order->status && null === $order->reviewReason && 'confirmed' !== $this->tickets->status($order->targetId, $order->personId, $order->id) && array_sum(array_map(fn ($c) => $c->amountMinor - $c->refundedMinor, $this->charges($order->id))) > 0) {
                    $this->review($order, 'The paid ticket was cancelled. Refund review is required; cancellation does not move money.');
                }
            }
        });
    }

    private function review(PaymentOrder $order, string $reason): void
    {
        $this->records->save($order->change(['status' => 'review', 'reviewReason' => $reason]));
    }

    private function charges(string $id): array
    {
        return array_values(array_filter($this->records->all('charges'), fn ($charge) => $charge->orderId === $id));
    }
}
