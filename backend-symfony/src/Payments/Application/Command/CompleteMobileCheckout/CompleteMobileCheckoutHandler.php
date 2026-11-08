<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\CompleteMobileCheckout;

use App\Giving\Application\Contract\DonorCheckoutGuard;
use App\IdentityAccess\Application\Contract\AccountDirectory;
use App\IdentityAccess\Application\Contract\SessionReader;
use App\IdentityAccess\Application\Contract\TokenCodec;
use App\Payments\Application\Contract\CheckoutCodec;
use App\Payments\Application\Contract\HostedCheckout;
use App\Payments\Application\Contract\SubscriptionVault;
use App\Payments\Domain\PaymentException;
use App\Payments\Domain\Repository\MobileCheckoutRepository;
use App\Payments\Domain\Repository\PaymentRepository;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/**
 * Trusted public entry point authenticated by a one-use, expiring capability bound to a live session and donor link.
 * Every failure of the capability is "missing" (the browser sees 410). Lock order: handoff, order, donor link.
 */
final readonly class CompleteMobileCheckoutHandler implements CommandHandler
{
    public function __construct(
        private MobileCheckoutRepository $repository,
        private PaymentRepository $orders,
        private SessionReader $sessions,
        private AccountDirectory $accounts,
        private DonorCheckoutGuard $donors,
        private TokenCodec $tokens,
        private SubscriptionVault $vault,
        private CheckoutCodec $codec,
        private ClockInterface $clock,
    ) {
    }

    public function handle(CompleteMobileCheckoutCommand $c): HostedCheckout
    {
        if (1 !== preg_match('/\A[A-Za-z0-9_-]{20,200}\z/', $c->token)) {
            throw PaymentException::missing();
        }
        $now = $this->clock->now();
        $link = $this->repository->lock($this->tokens->hash($c->token));
        if (null === $link || !$link->usableAt($now)) {
            throw PaymentException::missing();
        }
        $order = $this->orders->lock($link->orderId) ?? throw PaymentException::missing();
        $session = $this->sessions->findById($link->sessionId);
        if (!$this->accounts->active($link->accountId) || null === $session || $session->accountId !== $link->accountId || $session->expiresAt <= $now) {
            throw PaymentException::missing();
        }
        if (!$this->donors->lockEligible($link->accountId, $order->donorId)) {
            throw PaymentException::missing();
        }
        if (!$order->awaitingPayment()) {
            throw PaymentException::conflict('This checkout is no longer awaiting payment. Return to the app and refresh.');
        }
        $values = $this->codec->decode($this->vault->decrypt($link->encryptedCheckout));
        $url = $values['__checkout_url'] ?? '';
        unset($values['__checkout_url']);
        $this->repository->used($link->tokenHash, $now);
        $this->orders->audit($order->id, 'MOBILE_CHECKOUT_OPENED', 'One-time browser handoff opened; payment is not confirmed.', $link->accountId, $now);

        return new HostedCheckout((string) $order->checkoutId, $url, $values);
    }
}
