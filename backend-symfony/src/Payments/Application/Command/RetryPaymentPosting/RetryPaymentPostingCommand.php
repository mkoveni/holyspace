<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\RetryPaymentPosting;

final readonly class RetryPaymentPostingCommand
{
    public function __construct(public string $actorId, public string $orderId, public int $expectedVersion)
    {
    }
}
