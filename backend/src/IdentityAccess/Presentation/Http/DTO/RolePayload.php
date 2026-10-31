<?php

declare(strict_types=1);

namespace App\IdentityAccess\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RolePayload
{
    public function __construct(#[Assert\NotBlank] #[Assert\Length(max: 100)] public string $name, #[Assert\Length(max: 255)] public string $description = '')
    {
    }
}
