<?php

declare(strict_types=1);

namespace App\Events\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class TicketPricePayload
{
    public function __construct(#[Assert\PositiveOrZero] public int $expectedVersion, #[Assert\Range(min: 500, max: 100000000)] public ?int $amountMinor = null)
    {
    }
}
