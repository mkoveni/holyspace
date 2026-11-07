<?php

declare(strict_types=1);

namespace App\IdentityAccess\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ProfileRequest
{
    public function __construct(
        public string $email = '',
        public string $displayName = '',
        /** Optional for older clients: when absent the update is not version-checked. */
        #[Assert\PositiveOrZero]
        public ?int $expectedVersion = null,
    ) {
    }
}
