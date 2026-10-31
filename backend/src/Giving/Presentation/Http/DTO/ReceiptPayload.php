<?php

declare(strict_types=1);

namespace App\Giving\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ReceiptPayload
{
    public function __construct(#[Assert\Uuid] public string $giftId)
    {
    }
}
