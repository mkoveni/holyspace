<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

use App\Payments\Domain\PaymentOrder;

/**
 * Hosted provider gateway (Java `PaymentGateway`). Every method that may reach the provider is called outside any
 * transaction. `verify` authenticates the unchanged raw body before anything is written.
 */
interface PaymentGateway
{
    /** @return list<PaymentProvider> */
    public function providers(): array;

    /** The environment new checkouts of this provider use; 503 when it is disabled or not configured. */
    public function mode(string $provider): string;

    /** Rechecks that the order's original configuration can still create a checkout; 503 otherwise. */
    public function validateOrder(PaymentOrder $order): void;

    public function checkout(PaymentOrder $order, string $donorName, string $email): HostedCheckout;

    /**
     * @param array<string, string> $headers lower-case names
     *
     * @return ProviderNotification|null null for an authenticated event Payments does not act on
     */
    public function verify(string $provider, string $rawBody, array $headers, string $ip): ?ProviderNotification;
}
