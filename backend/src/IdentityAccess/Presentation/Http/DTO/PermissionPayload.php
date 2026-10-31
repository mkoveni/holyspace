<?php

declare(strict_types=1);

namespace App\IdentityAccess\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PermissionPayload
{
    public function __construct(#[Assert\NotBlank] #[Assert\Length(max: 100)] public string $code, #[Assert\Length(max: 255)] public string $description = '')
    {
    }
}
