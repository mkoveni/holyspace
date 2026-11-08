<?php

declare(strict_types=1);

namespace App\Payments\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Java `PaymentPostingApi.Body`. */
final readonly class VersionRequest
{
    public function __construct(#[Assert\PositiveOrZero] public int $expectedVersion)
    {
    }
}
