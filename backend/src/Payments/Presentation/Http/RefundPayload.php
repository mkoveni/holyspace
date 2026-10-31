<?php

declare(strict_types=1);

namespace App\Payments\Presentation\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RefundPayload
{
    public function __construct(#[Assert\Positive] public int $expectedVersion, #[Assert\Range(min: 1, max: 100000000)] public int $amountMinor, #[Assert\NotBlank, Assert\Length(max: 150)] public string $reference, #[Assert\IsTrue] public bool $confirmed)
    {
    }
}
