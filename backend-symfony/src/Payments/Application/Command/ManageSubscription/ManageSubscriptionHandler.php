<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\ManageSubscription;

use App\Payments\Application\Contract\PaymentGateway;
use App\Payments\Application\Contract\SubscriptionVault;
use App\Payments\Application\PaymentAccess;
use App\Payments\Domain\PaymentException;
use App\Payments\Domain\Repository\PaymentRepository;
use App\Shared\Application\UnitOfWork;
use Psr\Clock\ClockInterface;

/**
 * Refreshes or cancels a Payfast monthly arrangement. A cancellation is recorded as requested first (and stays
 * requested if the provider cannot confirm it), the provider is called outside any transaction, and only its confirmed
 * status is saved. Provider boundary: not on the command bus.
 */
final readonly class ManageSubscriptionHandler
{
    public function __construct(
        private PaymentAccess $access,
        private PaymentRepository $repository,
        private UnitOfWork $transactions,
        private ClockInterface $clock,
        private PaymentGateway $gateway,
        private SubscriptionVault $vault,
    ) {
    }

    public function handle(ManageSubscriptionCommand $c): void
    {
        $this->access->caller($c->actorId, $c->selfService);
        $order = $this->transactions->execute(function () use ($c) {
            $current = $this->repository->lock($c->orderId) ?? throw PaymentException::missing();
            $this->access->order($c->actorId, $current, $c->selfService, true);
            $current->expected($c->expectedVersion);
            if (!$current->recurring) {
                throw PaymentException::invalid('This is a one-off checkout.');
            }
            if ($c->cancel && 'CANCELLED' !== $current->subscriptionStatus) {
                $this->repository->subscription($current->id, 'CANCEL_REQUESTED', $current->encryptedToken, $current->tokenHash);
                $this->repository->audit($current->id, 'CANCELLATION_REQUESTED', 'Awaiting provider confirmation.', $c->actorId, $this->clock->now());
            }

            return $current;
        });
        if ('CANCELLED' === $order->subscriptionStatus) {
            return;
        }
        if (null === $order->encryptedToken) {
            throw PaymentException::conflict('Subscription token is not confirmed yet. Cancellation remains requested; retry after provider confirmation.');
        }
        $status = $this->gateway->subscription($order, $this->vault->decrypt($order->encryptedToken), $c->cancel);
        $this->transactions->execute(function () use ($c, $order, $status): void {
            $current = $this->repository->lock($order->id) ?? throw PaymentException::missing();
            $this->access->order($c->actorId, $current, $c->selfService, true);
            if ('CANCELLED' === $current->subscriptionStatus) {
                return;
            }
            $result = !$c->cancel && 'CANCEL_REQUESTED' === $current->subscriptionStatus && 'CANCELLED' !== $status ? 'CANCEL_REQUESTED' : $status;
            $this->repository->subscription($order->id, $result, $current->encryptedToken, $current->tokenHash);
            $this->repository->audit($order->id, 'SUBSCRIPTION_CONFIRMED', $result, $c->actorId, $this->clock->now());
        });
    }
}
