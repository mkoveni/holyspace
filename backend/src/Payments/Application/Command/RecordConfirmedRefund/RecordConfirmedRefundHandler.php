<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\RecordConfirmedRefund;

use App\Events\Application\Port\TicketReservations;
use App\Giving\Application\Port\OnlineGivingLedger;
use App\Payments\Domain\Model\PaymentOrder;
use App\Payments\Domain\Model\PaymentRefund;
use App\Payments\Domain\Repository\PaymentRepository;
use App\Shared\Application\WorkflowTransaction;
use App\Shared\Domain\ValueObject\Input;
use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class RecordConfirmedRefundHandler
{
    public function __construct(
        private PaymentRepository $records,
        private OnlineGivingLedger $giving,
        private TicketReservations $tickets,
        private WorkflowTransaction $transactions,
        private ClockInterface $clock,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(RecordConfirmedRefundCommand $message): void
    {
        $chargeId = $message->chargeId;
        $amount = $message->amount;
        $reference = $message->reference;
        $version = $message->version;
        $confirmed = $message->confirmed;
        if (!$confirmed) {
            throw new InvalidArgumentException('Confirm that the refund completed in the provider dashboard.');
        }
        Input::text($reference, 150);
        $this->transactions->run(function () use ($chargeId, $amount, $reference, $version) {
            $charge = $this->records->find('charges', $chargeId) ?? throw new OutOfBoundsException('Charge not found.');
            $this->version($charge->version, $version);
            $this->recordRefund($this->order($charge->orderId), $reference, $amount, $chargeId);
        });
    }

    private function version(int $actual, int $expected): void
    {
        if ($actual !== $expected) {
            throw new DomainException('This payment changed. Reload before retrying.');
        }
    }

    private function recordRefund(PaymentOrder $order, string $reference, int $amount, ?string $chargeId, ?string $receivedOn = null): void
    {
        $key = $order->provider.':'.$order->mode.':'.$reference;
        $prior = $this->records->byExternalKey('refund:'.hash('sha256', $key));
        if ($prior) {
            if ($prior->orderId !== $order->id || $prior->amountMinor !== $amount) {
                $this->review($order, 'Refund reference conflicts with another refund.');

                return;
            }
            if (!$prior->applied && null === $prior->chargeId && null !== $chargeId) {
                $this->records->save($prior->matchCharge($chargeId));
                $this->applyRefunds($order);
            }

            return;
        }
        $refund = new PaymentRefund($order->id, $key, $amount, $receivedOn ?? $this->clock->now()->format('Y-m-d'), $chargeId);
        $this->records->save($refund);
        $this->applyRefunds($order);
    }

    private function order(string $id): PaymentOrder
    {
        return $this->records->find('orders', $id) ?? throw new OutOfBoundsException('Payment not found.');
    }

    private function review(PaymentOrder $order, string $reason): void
    {
        $this->records->save($order->change(['status' => 'review', 'reviewReason' => $reason]));
    }

    private function applyRefunds(PaymentOrder $order): void
    {
        foreach ($this->records->all('refunds') as $refund) {
            if ($refund->orderId !== $order->id || $refund->applied) {
                continue;
            }
            $charges = $this->charges($order->id);
            if (null !== $refund->chargeId) {
                $charges = array_values(array_filter($charges, fn ($c) => $c->id === $refund->chargeId));
            }
            if (1 !== count($charges)) {
                $this->review($this->order($order->id), 'A verified refund needs a matching, unambiguous charge. Reconcile the provider record.');
                continue;
            }
            $charge = $charges[0];
            if ($refund->amountMinor > $charge->amountMinor - $charge->refundedMinor) {
                $this->review($this->order($order->id), 'Refund amount exceeds the unrefunded charge. Review existing ledger reversals.');
                continue;
            }
            try {
                if ('test' === $order->mode) {
                    /* Sandbox refunds do not touch the real ledger. */
                } elseif ('giving' === $order->purpose) {
                    if (null === $charge->giftId) {
                        throw new DomainException('The original payment has not been posted as a gift.');
                    }
                    $this->giving->refund($charge->giftId, $refund->amountMinor, $refund->providerReference, max($refund->receivedOn, $charge->receivedOn));
                } elseif ($refund->amountMinor + $charge->refundedMinor === $charge->amountMinor) {
                    $this->tickets->release($order->targetId, $order->personId, $order->id);
                }
                $this->records->save($charge->refunded($refund->amountMinor));
                $this->records->save($refund->complete());
            } catch (DomainException|InvalidArgumentException|OutOfBoundsException $e) {
                $this->review($this->order($order->id), 'Refund is verified but ledger reconciliation is required. '.$e->getMessage());
            }
        }
    }

    private function charges(string $id): array
    {
        return array_values(array_filter($this->records->all('charges'), fn ($charge) => $charge->orderId === $id));
    }
}
