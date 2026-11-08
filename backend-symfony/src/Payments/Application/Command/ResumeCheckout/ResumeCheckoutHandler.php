<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\ResumeCheckout;

use App\Payments\Application\Contract\CheckoutCodec;
use App\Payments\Application\Contract\HostedCheckout;
use App\Payments\Application\Contract\PaymentGateway;
use App\Payments\Application\PaymentAccess;
use App\Payments\Domain\PaymentException;
use App\Payments\Domain\PaymentOrder;
use App\Payments\Domain\Repository\PaymentRepository;
use App\People\Application\Contract\PersonDirectory;
use App\People\Application\Contract\PersonEmailDirectory;
use App\Shared\Application\UnitOfWork;
use Exception;
use Psr\Clock\ClockInterface;

/**
 * Creates (once) or returns the hosted checkout of an order. Provider boundary: not on the command bus; the provider
 * call runs outside any transaction and its outcome is recorded in a second one, under the order lock. A failed call
 * is recorded for review and the same request may be retried; the provider's idempotency key is the order id.
 */
final readonly class ResumeCheckoutHandler
{
    public function __construct(
        private PaymentAccess $access,
        private PaymentRepository $repository,
        private UnitOfWork $transactions,
        private ClockInterface $clock,
        private PaymentGateway $gateway,
        private PersonDirectory $people,
        private PersonEmailDirectory $emails,
        private CheckoutCodec $codec,
    ) {
    }

    public function handle(ResumeCheckoutCommand $c): HostedCheckout
    {
        $this->access->caller($c->actorId, $c->selfService);
        $order = $this->repository->find($c->orderId) ?? throw PaymentException::missing();
        $this->access->order($c->actorId, $order, $c->selfService, false);
        if (!\in_array($order->status, PaymentOrder::OPEN, true)) {
            throw PaymentException::conflict('This payment already finished. Refresh its verified history.');
        }
        if (\in_array($order->subscriptionStatus, PaymentOrder::CANCELLING, true)) {
            throw PaymentException::conflict('Monthly giving cancellation is requested. Refresh the subscription instead of resuming checkout.');
        }
        $this->gateway->validateOrder($order);
        if (null !== $order->checkoutUrl) {
            return $this->existing($order);
        }
        $name = $this->people->get($order->donorId)->name ?? throw PaymentException::missing();
        $email = ($this->emails->active($order->donorId)[0] ?? null)->value ?? '';
        try {
            $checkout = $this->gateway->checkout($order, $name, $email);
        } catch (Exception $error) {
            $this->transactions->execute(function () use ($c, $order): void {
                $current = $this->repository->lock($order->id) ?? throw PaymentException::missing();
                $this->access->order($c->actorId, $current, $c->selfService, true);
                $this->repository->review($order->id, 'Checkout creation is unconfirmed. Retry this same request; check the provider before creating another.');
            });
            throw $error;
        }

        return $this->transactions->execute(function () use ($c, $order, $checkout): HostedCheckout {
            $current = $this->repository->lock($order->id) ?? throw PaymentException::missing();
            $this->access->order($c->actorId, $current, $c->selfService, true);
            if (!\in_array($current->status, PaymentOrder::OPEN, true)) {
                throw PaymentException::conflict('Payment confirmation arrived. Refresh its history.');
            }
            if (\in_array($current->subscriptionStatus, PaymentOrder::CANCELLING, true)) {
                throw PaymentException::conflict('Monthly giving cancellation was requested while checkout was being created.');
            }
            if (null !== $current->checkoutUrl) {
                return $this->existing($current);
            }
            $this->repository->ready($order->id, $checkout->id, $checkout->url, $this->codec->encode($checkout->fields));
            $this->repository->review($order->id, null);
            $this->repository->audit($order->id, 'CHECKOUT_READY', 'Hosted checkout confirmed; awaiting provider payment verification.', $c->actorId, $this->clock->now());

            return $checkout;
        });
    }

    private function existing(PaymentOrder $order): HostedCheckout
    {
        return new HostedCheckout((string) $order->checkoutId, (string) $order->checkoutUrl, $this->codec->decode($order->checkoutFields));
    }
}
