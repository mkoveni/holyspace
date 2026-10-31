<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\ReconcilePayment;

use App\Events\Application\Port\TicketReservations;
use App\Giving\Application\Port\OnlineGivingLedger;
use App\Payments\Domain\Model\PaymentCharge;
use App\Payments\Domain\Model\PaymentOrder;
use App\Payments\Domain\Repository\PaymentRepository;
use App\Shared\Application\WorkflowTransaction;
use App\Shared\Domain\ValueObject\Input;
use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class ReconcilePaymentHandler
{
    public function __construct(
        private PaymentRepository $records,
        private OnlineGivingLedger $giving,
        private TicketReservations $tickets,
        private WorkflowTransaction $transactions,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(ReconcilePaymentCommand $message): array
    {
        $id = $message->id;
        $version = $message->version;
        $note = $message->note;
        Input::text($note, 1000);

        return $this->transactions->run(function () use ($id, $version, $note) {
            $order = $this->order($id);
            $this->version($order->version, $version);
            foreach ($this->charges($id) as $charge) {
                if ('live' === $order->mode && 'giving' === $order->purpose && null === $charge->giftId) {
                    $gift = $this->giving->record($order->personId, $order->targetId, $charge->amountMinor, $charge->providerReference, $charge->receivedOn);
                    $this->records->save(new PaymentCharge(...array_replace(get_object_vars($charge), ['giftId' => $gift])));
                }
            }
            $this->applyRefunds($order);
            foreach ($this->records->all('refunds') as $refund) {
                if ($refund->orderId === $id && !$refund->applied) {
                    throw new DomainException('Resolve the unmatched refund before closing review.');
                }
            }
            if ('live' === $order->mode && 'ticket' === $order->purpose && 'confirmed' !== $this->tickets->status($order->targetId, $order->personId, $id) && array_sum(array_map(fn ($charge) => $charge->amountMinor - $charge->refundedMinor, $this->charges($id))) > 0) {
                throw new DomainException('A paid ticket without a confirmed place requires a completed refund before review can close.');
            }
            $current = $this->order($id);
            $this->records->save($current->change(['reviewReason' => null, 'status' => [] !== $this->charges($id) ? 'paid' : 'failed', 'reconciliationNote' => $note]));

            return $this->view($this->order($id));
        });
    }

    private function order(string $id): PaymentOrder
    {
        return $this->records->find('orders', $id) ?? throw new OutOfBoundsException('Payment not found.');
    }

    private function version(int $actual, int $expected): void
    {
        if ($actual !== $expected) {
            throw new DomainException('This payment changed. Reload before retrying.');
        }
    }

    private function charges(string $id): array
    {
        return array_values(array_filter($this->records->all('charges'), fn ($charge) => $charge->orderId === $id));
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

    private function view(PaymentOrder $order, ?array $charges = null, ?array $refunds = null): array
    {
        return ['id' => $order->id, 'personId' => $order->personId, 'provider' => $order->provider, 'mode' => $order->mode, 'purpose' => $order->purpose, 'targetId' => $order->targetId, 'requestKey' => $order->requestKey, 'amountMinor' => $order->amountMinor, 'currency' => 'ZAR', 'status' => $order->status, 'recurring' => $order->recurring, 'subscriptionStatus' => $order->subscriptionStatus, 'expiresAt' => $order->expiresAt, 'createdAt' => $order->createdAt, 'reviewReason' => $order->reviewReason, 'reconciliationNote' => $order->reconciliationNote, 'version' => $order->version, 'charges' => $charges ?? $this->charges($order->id), 'refunds' => $refunds ?? array_values(array_filter($this->records->all('refunds'), fn ($refund) => $refund->orderId === $order->id))];
    }

    private function review(PaymentOrder $order, string $reason): void
    {
        $this->records->save($order->change(['status' => 'review', 'reviewReason' => $reason]));
    }
}
