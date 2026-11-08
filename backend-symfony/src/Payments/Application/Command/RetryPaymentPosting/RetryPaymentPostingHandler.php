<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\RetryPaymentPosting;

use App\Payments\Application\PaymentAccess;
use App\Payments\Application\PaymentPosting;
use App\Payments\Domain\PaymentException;
use App\Payments\Domain\Repository\PaymentRepository;
use App\Shared\Application\UnitOfWork;
use Psr\Clock\ClockInterface;

/** Retries posting verified live evidence to Giving; never creates another checkout. Three transactions, not on the bus. */
final readonly class RetryPaymentPostingHandler
{
    public function __construct(
        private PaymentAccess $access,
        private PaymentRepository $repository,
        private PaymentPosting $posting,
        private UnitOfWork $transactions,
        private ClockInterface $clock,
    ) {
    }

    public function handle(RetryPaymentPostingCommand $c): PostingResult
    {
        $this->access->manage($c->actorId);
        $this->transactions->execute(function () use ($c): void {
            $order = $this->repository->lock($c->orderId) ?? throw PaymentException::missing();
            $this->access->manage($c->actorId);
            $order->expected($c->expectedVersion);
            if (!$order->isLive() || [] === $this->repository->charges($order->id)) {
                throw PaymentException::conflict('Only a verified live payment can be posted to Giving.');
            }
        });
        $this->posting->post($c->orderId, fn () => $this->access->manage($c->actorId));

        return $this->transactions->execute(function () use ($c): PostingResult {
            $this->repository->lock($c->orderId) ?? throw PaymentException::missing();
            $this->access->manage($c->actorId);
            $complete = [] === $this->repository->pendingCharges($c->orderId) && [] === $this->repository->pendingRefunds($c->orderId);
            $this->repository->audit($c->orderId, 'POSTING_RETRIED', $complete ? 'Verified evidence posted to Giving.'
                : 'Posting still needs attention; do not collect payment again.', $c->actorId, $this->clock->now());

            return new PostingResult($complete, $complete ? 'Contribution recorded in Giving.'
                : 'Posting could not be completed. Verified payment evidence is retained; do not collect payment again.');
        });
    }
}
