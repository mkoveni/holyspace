<?php

declare(strict_types=1);

namespace App\Payments\Application;

use App\Payments\Application\Command\ResumeCheckout\ResumeCheckoutCommand;
use App\Payments\Application\Command\ResumeCheckout\ResumeCheckoutHandler;
use App\Payments\Application\Contract\HostedCheckout;
use App\Payments\Application\Contract\PaymentConfigurationBinding;
use App\Payments\Application\Contract\PaymentGateway;
use App\Payments\Application\Contract\TicketPayments;
use App\Payments\Domain\PaymentException;
use App\Payments\Domain\PaymentOrder;
use App\Payments\Domain\Repository\PaymentRepository;
use Psr\Clock\ClockInterface;

/** Ticket purpose of the payments order: the Yoco intent for a public ticket order. */
final readonly class TicketPaymentOrders implements TicketPayments
{
    public function __construct(
        private PaymentRepository $repository,
        private PaymentGateway $gateway,
        private PaymentConfigurationBinding $configurations,
        private ResumeCheckoutHandler $checkouts,
        private ClockInterface $clock,
    ) {
    }

    public function open(string $ticketOrderId, int $amountMinor, ?string $buyerName, string $encryptedEmail, string $emailHash, string $accessHash, string $requestHash): void
    {
        $provider = PaymentOrder::PROVIDERS[0];
        $prior = $this->repository->find($ticketOrderId);
        if (null !== $prior) {
            if (PaymentOrder::TICKET !== $prior->purpose || $prior->requestHash !== $requestHash) {
                throw PaymentException::conflict('This request identifier was used for different payment details.');
            }

            return;
        }
        $mode = $this->gateway->mode($provider);
        $order = PaymentOrder::startTicket(
            $ticketOrderId,
            $amountMinor,
            $provider,
            $mode,
            $requestHash,
            $this->clock->now(),
            $this->configurations->bind($provider, $mode, false),
            $buyerName,
            $encryptedEmail,
            $emailHash,
            $accessHash
        );
        if (!$this->repository->insert($order)) {
            throw PaymentException::conflict('This request identifier was already used.');
        }
        $this->repository->audit($order->id, 'CHECKOUT_REQUESTED', 'Public ticket checkout requested.', null, $order->createdAt);
    }

    public function checkout(string $ticketOrderId): HostedCheckout
    {
        return $this->checkouts->handle(new ResumeCheckoutCommand(PaymentOrder::GUEST_ACTOR, $ticketOrderId, false, true));
    }
}
