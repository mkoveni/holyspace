<?php

declare(strict_types=1);

namespace App\Giving\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PledgeAllocationPayload
{
    public function __construct(#[Assert\Uuid] public string $pledgeId, #[Assert\Uuid] public string $giftId, #[Assert\Positive] public int $amountMinor, #[Assert\NotBlank, Assert\Length(max: 100)] public string $reference)
    {
    }
}
