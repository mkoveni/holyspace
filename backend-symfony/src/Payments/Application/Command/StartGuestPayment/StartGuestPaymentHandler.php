<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\StartGuestPayment;

use App\Giving\Application\Contract\OnlineFundDirectory;
use App\Giving\Application\Contract\OnlineGivingLedger;
use App\Payments\Application\Contract\GuestAccess;
use App\Payments\Application\Contract\PaymentConfigurationBinding;
use App\Payments\Application\Contract\PaymentGateway;
use App\Payments\Application\Contract\SubscriptionVault;
use App\Payments\Application\PaymentHash;
use App\Payments\Domain\PaymentException;
use App\Payments\Domain\PaymentOrder;
use App\Payments\Domain\Repository\PaymentRepository;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/**
 * Records a public, one-off checkout intent: no person is read, created or linked, and no gift is posted. The giver's
 * email is compulsory (their confirmation goes there) and is kept encrypted. Idempotent on the request id.
 */
final readonly class StartGuestPaymentHandler implements CommandHandler
{
    public function __construct(
        private PaymentRepository $repository,
        private ClockInterface $clock,
        private PaymentGateway $gateway,
        private OnlineGivingLedger $giving,
        private OnlineFundDirectory $funds,
        private PaymentConfigurationBinding $configurations,
        private SubscriptionVault $vault,
        private GuestAccess $access,
    ) {
    }

    public function handle(StartGuestPaymentCommand $c): string
    {
        $email = self::email($c->email);
        $name = self::name($c->name);
        $prior = $this->repository->find($c->requestId);
        $mode = null === $prior ? $this->gateway->mode($c->provider) : $prior->mode;
        $emailHash = $this->access->emailHash($email);
        $hash = PaymentHash::of('guest-checkout', $c->fundId, $c->amountMinor, $c->provider, $mode, $emailHash, $name);
        if (null !== $prior) {
            if (!$prior->isGuest() || $prior->requestHash !== $hash) {
                throw PaymentException::conflict('This request identifier was used for different payment details.');
            }

            return $prior->id;
        }
        PaymentOrder::amount($c->amountMinor);
        $configurationId = $this->configurations->bind($c->provider, $mode, false);
        if (!$this->funds->enabled($c->fundId)) {
            throw PaymentException::invalid('This fund is not currently offered online.');
        }
        $this->giving->validateFund($c->fundId);
        $order = PaymentOrder::startGuest(
            $c->requestId,
            $c->fundId,
            $c->amountMinor,
            $c->provider,
            $mode,
            $hash,
            $this->clock->now(),
            $configurationId,
            $name,
            $this->vault->encrypt($email),
            $emailHash,
            $this->access->hash($this->access->token($c->requestId))
        );
        if (!$this->repository->insert($order)) {
            $existing = $this->repository->find($order->id);
            if (null === $existing || !$existing->isGuest() || $existing->requestHash !== $hash) {
                throw PaymentException::conflict('This request identifier was already used.');
            }

            return $existing->id;
        }
        $this->repository->audit($order->id, 'CHECKOUT_REQUESTED', 'Public hosted checkout requested; no gift posted.', null, $order->createdAt);

        return $order->id;
    }

    public static function email(string $value): string
    {
        $email = strtolower(trim($value));
        if ('' === $email || \strlen($email) > 254 || false === filter_var($email, \FILTER_VALIDATE_EMAIL) || 1 === preg_match('/[\x00-\x1f\x7f]/', $email)) {
            throw PaymentException::invalid('Enter a valid email address for your confirmation.');
        }

        return $email;
    }

    private static function name(?string $value): ?string
    {
        $name = null === $value ? '' : trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if ('' === $name) {
            return null;
        }
        if (mb_strlen($name) > 100 || 1 === preg_match('/[\x00-\x1f\x7f<>]/', $name)) {
            throw PaymentException::invalid('Enter a name of at most 100 characters.');
        }

        return $name;
    }
}
