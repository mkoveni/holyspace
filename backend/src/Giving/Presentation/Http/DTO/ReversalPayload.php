<?php

declare(strict_types=1);

namespace App\Giving\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ReversalPayload
{
    public function __construct(public string $reversedOn, #[Assert\NotBlank] public string $reason)
    {
    }
}
