<?php

declare(strict_types=1);

namespace App\Events\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CancelRegistrationPayload
{
    public function __construct(#[Assert\NotBlank, Assert\Length(max: 1000)] public string $reason, #[Assert\Positive] public int $expectedVersion)
    {
    }
}
