<?php

declare(strict_types=1);

namespace App\IdentityAccess\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class LoginRequest
{
    public function __construct(
        #[Assert\NotBlank, Assert\Length(max: 254)]
        public string $username = '',
        #[Assert\NotBlank, Assert\Length(max: 72)]
        public string $password = '',
        #[Assert\Length(max: 80)]
        public ?string $deviceName = null,
    ) {
    }
}
