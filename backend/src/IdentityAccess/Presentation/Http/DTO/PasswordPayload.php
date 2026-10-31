<?php

declare(strict_types=1);

namespace App\IdentityAccess\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PasswordPayload
{
    public function __construct(#[Assert\NotBlank] #[Assert\Length(min: 12, max: 4096)] public string $password)
    {
    }
}
