<?php

declare(strict_types=1);

namespace App\IdentityAccess\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SecurityPayload
{
    public function __construct(#[Assert\Length(max: 4096)] public string $password = '', #[Assert\Length(max: 64)] public string $code = '', #[Assert\Length(max: 4096)] public string $newPassword = '')
    {
    }
}
