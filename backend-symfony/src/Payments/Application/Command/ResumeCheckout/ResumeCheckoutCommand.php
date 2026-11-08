<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\ResumeCheckout;

final readonly class ResumeCheckoutCommand
{
    public function __construct(public string $actorId, public string $orderId, public bool $selfService = false)
    {
    }
}
