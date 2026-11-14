<?php

declare(strict_types=1);

namespace App\Tests\Payments\Support;

use App\Payments\Application\Contract\HostedCheckout;
use App\Payments\Application\Contract\PaymentGateway;
use App\Payments\Application\Contract\ProviderNotification;
use App\Payments\Domain\PaymentOrder;
use App\Payments\Infrastructure\Gateway\HostedPaymentGateway;
use Closure;
use Doctrine\DBAL\Connection;

/**
 * The real hosted gateway unless a test scripts a method (Java's @MockitoBean PaymentGateway). Once scripted, an
 * unscripted `validateOrder` does nothing, as a Mockito mock would. Records provider calls made inside a transaction.
 */
final class ScriptedPaymentGateway implements PaymentGateway
{
    public bool $scripted = false;
    public ?Closure $mode = null;
    public ?Closure $providers = null;
    public ?Closure $checkout = null;
    public ?Closure $verify = null;
    public int $checkouts = 0;
    public bool $inTransaction = false;

    public function __construct(private readonly HostedPaymentGateway $real, private readonly Connection $connection)
    {
    }

    public function reset(): void
    {
        $this->scripted = false;
        $this->mode = $this->providers = $this->checkout = $this->verify = null;
        $this->checkouts = 0;
        $this->inTransaction = false;
    }

    public function providers(): array
    {
        return null === $this->providers ? $this->real->providers() : ($this->providers)();
    }

    public function mode(string $provider): string
    {
        return null === $this->mode ? $this->real->mode($provider) : ($this->mode)($provider);
    }

    public function validateOrder(PaymentOrder $order): void
    {
        if (!$this->scripted) {
            $this->real->validateOrder($order);
        }
    }

    public function checkout(PaymentOrder $order, string $donorName, string $email): HostedCheckout
    {
        ++$this->checkouts;
        $this->record();

        return null === $this->checkout ? $this->real->checkout($order, $donorName, $email) : ($this->checkout)($order);
    }

    public function verify(string $provider, string $rawBody, array $headers, string $ip): ?ProviderNotification
    {
        $this->record();

        return null === $this->verify ? $this->real->verify($provider, $rawBody, $headers, $ip) : ($this->verify)();
    }

    private function record(): void
    {
        $this->inTransaction = $this->inTransaction || $this->connection->isTransactionActive();
    }
}
