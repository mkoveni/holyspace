<?php

declare(strict_types=1);

namespace App\IdentityAccess\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PasswordChangeRequest
{
    public function __construct(
        #[Assert\NotBlank, Assert\Length(max: 72)]
        public string $currentPassword = '',
        public string $password = '',
        #[Assert\PositiveOrZero]
        public ?int $expectedVersion = null,
    ) {
    }
}
