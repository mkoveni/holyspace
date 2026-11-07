<?php

declare(strict_types=1);

namespace App\IdentityAccess\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PasswordResetRequest
{
    public function __construct(
        public string $password = '',
        #[Assert\PositiveOrZero]
        public ?int $expectedVersion = null,
    ) {
    }
}
