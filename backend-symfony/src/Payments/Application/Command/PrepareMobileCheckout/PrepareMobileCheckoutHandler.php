<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\PrepareMobileCheckout;

use App\IdentityAccess\Application\Contract\CurrentActor;
use App\IdentityAccess\Application\Contract\TokenCodec;
use App\Payments\Application\Command\ResumeCheckout\ResumeCheckoutCommand;
use App\Payments\Application\Command\ResumeCheckout\ResumeCheckoutHandler;
use App\Payments\Application\Contract\CheckoutCodec;
use App\Payments\Application\Contract\MobileCheckoutOrigin;
use App\Payments\Application\Contract\SubscriptionVault;
use App\Payments\Application\PaymentAccess;
use App\Payments\Domain\MobileCheckout;
use App\Payments\Domain\PaymentException;
use App\Payments\Domain\Repository\MobileCheckoutRepository;
use App\Payments\Domain\Repository\PaymentRepository;
use App\Shared\Application\UnitOfWork;
use Psr\Clock\ClockInterface;

/**
 * Prepares a one-time, five-minute browser handoff of the donor's own checkout, bound to the caller's live session.
 * Only the token hash is stored and the checkout fields are encrypted. Provider boundary: resuming the checkout may call
 * the provider, so this is not on the command bus.
 */
final readonly class PrepareMobileCheckoutHandler
{
    private const int LIFETIME_SECONDS = 300;

    public function __construct(
        private PaymentAccess $access,
        private ResumeCheckoutHandler $resume,
        private PaymentRepository $orders,
        private MobileCheckoutRepository $repository,
        private CurrentActor $actor,
        private TokenCodec $tokens,
        private SubscriptionVault $vault,
        private MobileCheckoutOrigin $origins,
        private UnitOfWork $transactions,
        private ClockInterface $clock,
        private CheckoutCodec $codec,
    ) {
    }

    public function handle(PrepareMobileCheckoutCommand $c): MobileHandoff
    {
        $this->access->caller($c->actorId, true);
        $checkout = $this->resume->handle(new ResumeCheckoutCommand($c->actorId, $c->orderId, true));

        return $this->transactions->execute(function () use ($c, $checkout): MobileHandoff {
            $order = $this->orders->lock($c->orderId) ?? throw PaymentException::missing();
            $this->access->order($c->actorId, $order, true, true);
            if (!$order->awaitingPayment()) {
                throw PaymentException::conflict('Refresh this payment before opening checkout.');
            }
            $session = $this->actor->current()->sessionId ?? throw PaymentException::missing();
            $token = $this->tokens->generate();
            $expires = $this->clock->now()->modify('+'.self::LIFETIME_SECONDS.' seconds');
            $values = $checkout->fields;
            $values['__checkout_url'] = $checkout->url;
            $this->repository->insert(new MobileCheckout(
                $this->tokens->hash($token),
                $order->id,
                $c->actorId,
                $session,
                $this->vault->encrypt($this->codec->encode($values)),
                $expires,
                null
            ));

            return new MobileHandoff($this->origins->backend($order).'/api/mobile-checkouts/'.urlencode($token), $expires);
        });
    }
}
