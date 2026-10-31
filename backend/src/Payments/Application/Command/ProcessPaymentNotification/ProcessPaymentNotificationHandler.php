<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\ProcessPaymentNotification;

use App\Events\Application\Port\TicketReservations;
use App\Giving\Application\Port\OnlineGivingLedger;
use App\Payments\Application\Port\PaymentGateway;
use App\Payments\Application\Port\SubscriptionTokens;
use App\Payments\Domain\Model\PaymentCharge;
use App\Payments\Domain\Model\PaymentOrder;
use App\Payments\Domain\Model\PaymentRefund;
use App\Payments\Domain\Repository\PaymentRepository;
use App\Shared\Application\WorkflowTransaction;
use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;
use RuntimeException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

final readonly class ProcessPaymentNotificationHandler
{
    public function __construct(
        private PaymentRepository $records,
        private PaymentGateway $gateway,
        private OnlineGivingLedger $giving,
        private TicketReservations $tickets,
        private WorkflowTransaction $transactions,
        private ClockInterface $clock,
        private SubscriptionTokens $vault,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(ProcessPaymentNotificationCommand $message): void
    {
        $provider = $message->provider;
        $body = $message->body;
        $headers = $message->headers;
        $ip = $message->ip;
        $event = $this->gateway->verify($provider, $body, $headers, $ip);
        if (null === $event) {
            return;
        }
        $this->transactions->run(function () use ($event) {
            $order = $this->eventOrder($event);
            if (!$order) {
                return;
            }
            // Other transactions on the same merchant account are not church orders.
            $key = $event['provider'].':'.$event['mode'].':'.$event['eventId'];
            $fingerprint = hash('sha256', json_encode($event, JSON_THROW_ON_ERROR));
            $prior = $this->records->notification($key);
            if (null !== $prior) {
                if (!hash_equals($prior, $fingerprint)) {
                    $this->review($order, 'A repeated notification has conflicting payment details.');
                }

                return;
            }
            if ('ZAR' !== $event['currency'] || !in_array($event['type'], ['refunded', 'refund_failed'], true) && $event['amountMinor'] !== $order->amountMinor) {
                $this->review($order, 'Verified notification amount or currency does not match this order. No gift or ticket was posted.');
                $this->records->remember($key, $fingerprint);

                return;
            }
            if ('paid' === $event['type']) {
                $this->paid($order, $event);
            } elseif ('failed' === $event['type']) {
                if ([] === $this->charges($order->id)) {
                    $this->records->save($order->change(['status' => 'failed']));
                    if ('ticket' === $order->purpose) {
                        $this->tickets->release($order->targetId, $order->personId, $order->id);
                    }
                } elseif ($order->recurring) {
                    $this->review($order, 'A recurring charge failed. Review the subscription in Payfast.');
                }
            } elseif ('refunded' === $event['type']) {
                $this->recordRefund($order, $event['reference'], $event['amountMinor'], null, $event['receivedOn'] ?? null);
            } else {
                $this->review($order, 'The provider reported a failed refund. Check its dashboard.');
            }
            $this->records->remember($key, $fingerprint);
        });
    }

    private function eventOrder(array $event): ?PaymentOrder
    {
        if ('yoco' === $event['provider']) {
            foreach ($this->records->all('orders') as $order) {
                if ('yoco' === $order->provider && $order->mode === $event['mode'] && $order->checkoutId === $event['checkoutId']) {
                    return $order;
                }
            }
            if (is_string($event['orderId']) && Uuid::isValid($event['orderId'])) {
                $order = $this->records->find('orders', $event['orderId']);
                if ($order && 'yoco' === $order->provider && null === $order->checkoutId) {
                    throw new RuntimeException('Checkout confirmation is still pending. Retry this notification.');
                }
            }

            return null;
        }
        if (is_string($event['orderId']) && Uuid::isValid($event['orderId'])) {
            $order = $this->records->find('orders', $event['orderId']);

            return $order && 'payfast' === $order->provider && $order->mode === $event['mode'] ? $order : null;
        }
        if (null !== $event['token']) {
            foreach ($this->records->all('orders') as $order) {
                if ($order->recurring && $order->mode === $event['mode'] && null !== $order->encryptedToken && hash_equals($this->vault->decrypt($order->encryptedToken), $event['token'])) {
                    return $order;
                }
            }
        }

        return null;
    }

    private function review(PaymentOrder $order, string $reason): void
    {
        $this->records->save($order->change(['status' => 'review', 'reviewReason' => $reason]));
    }

    private function paid(PaymentOrder $order, array $event): void
    {
        $reference = $order->provider.':'.$order->mode.':'.$event['reference'];
        $existing = $this->records->byExternalKey('charge:'.hash('sha256', $reference));
        if ($existing) {
            if ($existing->orderId !== $order->id || $existing->amountMinor !== $event['amountMinor']) {
                $this->review($order, 'A provider payment reference conflicts with another charge.');
            }

            return;
        }
        $reason = $order->reviewReason;
        if (!$order->recurring && [] !== $this->charges($order->id)) {
            $reason = 'More than one successful charge was received for this one-off order. Review and refund any duplicate collection.';
        }
        if ($order->recurring && null === $event['token']) {
            $reason = 'The recurring payment arrived without its subscription token. Review Payfast before setting up another subscription.';
        }
        if (null !== $order->encryptedToken && null !== $event['token'] && !hash_equals($this->vault->decrypt($order->encryptedToken), $event['token'])) {
            $this->review($order, 'Subscription token conflicts with this order.');

            return;
        }
        $charge = new PaymentCharge($order->id, $reference, $event['amountMinor'], $event['receivedOn'] ?? $this->clock->now()->format('Y-m-d'));
        $this->records->save($charge);
        try {
            if ('test' === $order->mode) {
                if ('ticket' === $order->purpose) {
                    $this->tickets->release($order->targetId, $order->personId, $order->id);
                }
            } elseif ('giving' === $order->purpose) {
                $giftId = $this->giving->record($order->personId, $order->targetId, $charge->amountMinor, $reference, $charge->receivedOn);
                $saved = $this->records->find('charges', $charge->id);
                $this->records->save(new PaymentCharge(...array_replace(get_object_vars($saved), ['giftId' => $giftId])));
            } elseif (!$this->tickets->confirm($order->targetId, $order->personId, $order->id)) {
                $reason = 'Payment succeeded after the ticket reservation expired or was cancelled. No place was confirmed; review and refund in the provider dashboard.';
            }
        } catch (DomainException|InvalidArgumentException|OutOfBoundsException $e) {
            $reason = 'Payment is verified but could not be posted. '.$e->getMessage();
        }
        $changes = ['status' => null === $reason ? 'paid' : 'review', 'reviewReason' => $reason];
        if ($order->recurring && null !== $event['token']) {
            $changes['encryptedToken'] = $this->vault->encrypt($event['token']);
            if ('pending' === $order->subscriptionStatus) {
                $changes['subscriptionStatus'] = 'active';
            }
        }
        $this->records->save($this->order($order->id)->change($changes));
        $this->applyRefunds($this->order($order->id));
    }

    private function charges(string $id): array
    {
        return array_values(array_filter($this->records->all('charges'), fn ($charge) => $charge->orderId === $id));
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

    private function order(string $id): PaymentOrder
    {
        return $this->records->find('orders', $id) ?? throw new OutOfBoundsException('Payment not found.');
    }
}
