<?php

declare(strict_types=1);

namespace App\Giving\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class GiftReversalPayload
{
    public function __construct(#[Assert\Uuid] public string $giftId, public string $reversedOn, #[Assert\NotBlank] public string $reason)
    {
    }
}
