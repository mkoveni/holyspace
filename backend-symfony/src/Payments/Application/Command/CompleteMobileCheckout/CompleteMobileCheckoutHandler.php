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
use Psr\Log\LoggerInterface;

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
        private LoggerInterface $logger,
    ) {
    }

    public function handle(CompleteMobileCheckoutCommand $c): HostedCheckout
    {
        if (1 !== preg_match('/\A[A-Za-z0-9_-]{20,200}\z/', $c->token)) {
            throw $this->refused('malformed_token');
        }
        $now = $this->clock->now();
        $link = $this->repository->lock($this->tokens->hash($c->token));
        if (null === $link) {
            throw $this->refused('unknown_token');
        }
        if (null !== $link->usedAt) {
            throw $this->refused('already_used', $link->orderId);
        }
        if ($link->expiresAt <= $now) {
            throw $this->refused('expired', $link->orderId);
        }
        $order = $this->orders->lock($link->orderId) ?? throw $this->refused('order_missing', $link->orderId);
        if (!$this->accounts->active($link->accountId)) {
            throw $this->refused('account_inactive', $link->orderId);
        }
        $session = $this->sessions->findById($link->sessionId);
        if (null === $session) {
            throw $this->refused('session_gone', $link->orderId);
        }
        if ($session->accountId !== $link->accountId) {
            throw $this->refused('session_account_mismatch', $link->orderId);
        }
        if ($session->expiresAt <= $now) {
            throw $this->refused('session_expired', $link->orderId);
        }
        if (null === $order->donorId || !$this->donors->lockEligible($link->accountId, $order->donorId)) {
            throw $this->refused('donor_link_ineligible', $link->orderId);
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

    /**
     * Every refusal looks the same to the browser (410), so the reason is logged for operators. Never the token, the
     * checkout or a name: only a reason code and the order id.
     */
    private function refused(string $reason, ?string $orderId = null): PaymentException
    {
        $this->logger->warning('Mobile checkout handoff refused.', ['reason' => $reason, 'order' => $orderId]);

        return PaymentException::missing();
    }
}
