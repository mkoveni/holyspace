<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\PrepareMobileCheckout;

final readonly class PrepareMobileCheckoutCommand
{
    public function __construct(public string $actorId, public string $orderId)
    {
    }
}
