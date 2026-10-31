<?php

declare(strict_types=1);

namespace App\IdentityAccess\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RecoveryPayload
{
    public function __construct(#[Assert\NotBlank] #[Assert\Length(max: 255)] public string $username, #[Assert\NotBlank] #[Assert\Length(max: 64)] public string $code, #[Assert\Length(min: 12, max: 4096)] public string $newPassword)
    {
    }
}
