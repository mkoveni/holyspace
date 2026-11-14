<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\ProcessNotification;

use App\Payments\Application\Contract\PaymentGateway;
use App\Payments\Application\Contract\ProviderNotification;
use App\Payments\Application\PaymentHash;
use App\Payments\Application\PaymentPosting;
use App\Payments\Domain\Charge;
use App\Payments\Domain\PaymentException;
use App\Payments\Domain\PaymentOrder;
use App\Payments\Domain\Refund;
use App\Payments\Domain\Repository\PaymentRepository;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Application\IdGenerator;
use App\Shared\Application\UnitOfWork;
use Psr\Clock\ClockInterface;

/**
 * Public provider entry point. Only a successfully authenticated gateway notification enters a write transaction
 * (verification is outside any transaction), and durable verification
 * commits before posting to Giving starts in its own transaction. Provider boundary: not on the command bus.
 */
final readonly class ProcessNotificationHandler
{
    public function __construct(
        private PaymentRepository $repository,
        private UnitOfWork $transactions,
        private ClockInterface $clock,
        private ChurchCalendar $calendar,
        private IdGenerator $ids,
        private PaymentGateway $gateway,
        private PaymentPosting $posting,
    ) {
    }

    public function handle(ProcessNotificationCommand $c): void
    {
        $event = $this->gateway->verify($c->provider, $c->body, $c->headers, $c->ip);
        if (null === $event) {
            return;
        }
        $id = $this->resolve($event);
        if (null === $id) {
            return;
        }
        $this->transactions->execute(function () use ($event, $id): void {
            $this->record($event, $id);
        });
        $this->posting->post($id);
    }

    private function record(ProviderNotification $event, string $id): void
    {
        $order = $this->repository->lock($id) ?? throw PaymentException::missing();
        if ($order->provider !== $event->provider || $order->mode !== $event->mode) {
            throw PaymentException::invalid('Provider environment does not match the payment.');
        }
        if ('yoco' === $event->provider && (null === $order->checkoutId || $order->checkoutId !== $event->checkoutId)) {
            throw PaymentException::unavailable('Checkout confirmation is pending; retry this notification.');
        }
        $hash = PaymentHash::of(
            $event->provider,
            $event->mode,
            $event->type,
            $event->reference,
            $event->checkoutId,
            $event->orderId,
            $event->amountMinor,
            $event->currency,
            $event->date
        );
        $now = $this->clock->now();
        if (!$this->repository->remember($event->provider, $event->mode, $event->eventId, $id, $hash, $now)) {
            if ($this->repository->notification($event->provider, $event->mode, $event->eventId) !== $hash) {
                $this->repository->review($id, 'A repeated notification has conflicting financial details.');
            }

            return;
        }
        if ('ZAR' !== $event->currency || (!\in_array($event->type, ['REFUNDED', 'REFUND_FAILED'], true) && $event->amountMinor !== $order->amountMinor)) {
            $this->repository->review($id, 'Verified amount or currency does not match the checkout; no gift posted.');

            return;
        }
        match ($event->type) {
            'PAID' => $this->paid($order, $event),
            'REFUNDED' => $this->refund($order, $event),
            'FAILED' => $this->failed($order),
            default => $this->repository->review($id, 'The provider reported a failed refund. Check its dashboard.'),
        };
        $this->repository->audit($id, 'PROVIDER_'.$event->type, $event->reference, null, $now);
    }

    /** Untrusted hints only select a candidate; the event was already authenticated against that order's credentials. */
    private function resolve(ProviderNotification $e): ?string
    {
        if ('yoco' === $e->provider) {
            $found = $this->repository->lookup($e->provider, $e->mode, $e->checkoutId, null);
            if (null === $found && null !== $e->orderId && null !== $this->repository->find($e->orderId)) {
                throw PaymentException::unavailable('Checkout confirmation is pending; retry this notification.');
            }

            return $found;
        }

        return null;
    }

    private function failed(PaymentOrder $order): void
    {
        if ([] === $this->repository->charges($order->id)) {
            $this->repository->status($order->id, 'FAILED');
        }
    }

    private function paid(PaymentOrder $order, ProviderNotification $e): void
    {
        $prior = $this->repository->charge($e->provider, $e->mode, $e->reference);
        if (null !== $prior) {
            $this->conflicting($order, $prior, $e->amountMinor);

            return;
        }
        if ([] !== $this->repository->charges($order->id)) {
            $this->repository->review($order->id, 'More than one charge was collected for a one-off checkout. Review possible duplicate collection.');
        }
        $charge = new Charge(
            $this->ids->uuid(),
            $order->id,
            $order->provider,
            $order->mode,
            $e->reference,
            $e->amountMinor,
            $e->date ?? $this->calendar->today(),
            null,
            $this->clock->now()
        );
        if (!$this->repository->insertCharge($charge)) {
            $existing = $this->repository->charge($e->provider, $e->mode, $e->reference) ?? throw PaymentException::missing();
            $this->conflicting($order, $existing, $charge->amountMinor);

            return;
        }
        $this->repository->status($order->id, 'PAID');
        $this->repository->review($order->id, $order->isLive() ? 'Awaiting settlement review.' : null);
    }

    private function conflicting(PaymentOrder $order, Charge $charge, int $amount): void
    {
        if ($charge->orderId !== $order->id || $charge->amountMinor !== $amount) {
            $this->repository->review($order->id, 'Provider charge reference conflicts with another payment.');
        }
    }

    private function refund(PaymentOrder $order, ProviderNotification $e): void
    {
        $refund = new Refund(
            $this->ids->uuid(),
            $order->id,
            null,
            $order->provider,
            $order->mode,
            $e->reference,
            $e->amountMinor,
            $e->date ?? $this->calendar->today(),
            false,
            'Authenticated provider refund',
            null,
            $this->clock->now()
        );
        if ($this->repository->insertRefund($refund)) {
            return;
        }
        $prior = $this->repository->refund($order->provider, $order->mode, $e->reference) ?? throw PaymentException::missing();
        if ($prior->orderId !== $order->id || $prior->amountMinor !== $e->amountMinor) {
            $this->repository->review($order->id, 'Provider refund reference conflicts with existing evidence.');
        }
    }
}
