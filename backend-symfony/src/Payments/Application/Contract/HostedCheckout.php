<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

/** Where the donor's browser goes next: a Yoco redirect, or a signed Payfast form (Java `PaymentGateway.Checkout`). */
final readonly class HostedCheckout
{
    /** @param array<string, string> $fields in provider signing order */
    public function __construct(public string $id, public string $url, public array $fields)
    {
    }

    /** @return array{id: string} */
    public function __debugInfo(): array
    {
        return ['id' => $this->id];
    }
}
