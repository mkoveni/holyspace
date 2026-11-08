<?php

declare(strict_types=1);

namespace App\Payments\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SubscriptionRequest
{
    public function __construct(#[Assert\PositiveOrZero] public int $expectedVersion, public bool $cancel = false)
    {
    }
}
