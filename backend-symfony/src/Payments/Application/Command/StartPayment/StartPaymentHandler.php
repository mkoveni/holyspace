<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\StartPayment;

use App\Giving\Application\Contract\OnlineGivingLedger;
use App\Payments\Application\Contract\PaymentConfigurationBinding;
use App\Payments\Application\Contract\PaymentGateway;
use App\Payments\Application\PaymentAccess;
use App\Payments\Application\PaymentHash;
use App\Payments\Domain\PaymentException;
use App\Payments\Domain\PaymentOrder;
use App\Payments\Domain\Repository\PaymentRepository;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/**
 * Records hosted checkout intent; no provider call and no gift. Idempotent on the request id. Lock order: provider
 * settings head (pinning the configuration revision), then the donor link, then Giving's donor and fund locks.
 */
final readonly class StartPaymentHandler implements CommandHandler
{
    public function __construct(
        private PaymentAccess $access,
        private PaymentRepository $repository,
        private ClockInterface $clock,
        private PaymentGateway $gateway,
        private OnlineGivingLedger $giving,
        private PaymentConfigurationBinding $configurations,
    ) {
    }

    public function handle(StartPaymentCommand $c): string
    {
        $this->access->caller($c->actorId, $c->selfService);
        $prior = $this->repository->find($c->requestId);
        $mode = null === $prior ? $this->gateway->mode($c->provider) : $prior->mode;
        $hash = PaymentHash::of(
            $c->selfService ? 'donor-checkout' : 'checkout',
            $c->actorId,
            $c->donorId,
            $c->fundId,
            $c->amountMinor,
            $c->provider,
            $mode,
            $c->recurring
        );
        if (null !== $prior) {
            $this->access->order($c->actorId, $prior, $c->selfService, true);
            if ($prior->requestHash !== $hash) {
                throw PaymentException::conflict('This request identifier was used for different payment details.');
            }

            return $prior->id;
        }
        $configurationId = $this->configurations->bind($c->provider, $mode, $c->recurring);
        $this->access->start($c->actorId, $c->donorId, $c->fundId, $c->selfService, true);
        $this->giving->validate($c->donorId, $c->fundId);
        $this->access->start($c->actorId, $c->donorId, $c->fundId, $c->selfService, false);
        $now = $this->clock->now();
        $order = PaymentOrder::start(
            $c->requestId,
            $c->donorId,
            $c->fundId,
            $c->amountMinor,
            $c->provider,
            $mode,
            $c->recurring,
            $hash,
            $c->actorId,
            $now,
            $configurationId,
            $c->selfService
        );
        if (!$this->repository->insert($order)) {
            $existing = $this->repository->find($order->id)
                ?? throw PaymentException::conflict('A monthly arrangement is already active or awaiting confirmation for this donor and fund.');
            if ($existing->requestHash !== $hash) {
                throw PaymentException::conflict('This request identifier was already used.');
            }

            return $existing->id;
        }
        $this->repository->audit($order->id, 'CHECKOUT_REQUESTED', 'Hosted checkout requested; no gift posted.', $c->actorId, $now);

        return $order->id;
    }
}
