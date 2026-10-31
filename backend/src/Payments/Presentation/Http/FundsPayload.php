<?php

declare(strict_types=1);

namespace App\Payments\Presentation\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class FundsPayload
{
    public function __construct(#[Assert\All(constraints: [new Assert\Uuid()]), Assert\Count(max: 100)] public array $fundIds, #[Assert\PositiveOrZero] public int $expectedVersion)
    {
    }
}
