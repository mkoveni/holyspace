<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class VerifyEmailRequest
{
    public function __construct(
        #[Assert\NotNull, Assert\Uuid]
        public ?string $contactId = null,
        #[Assert\NotBlank, Assert\Length(max: 500)]
        public ?string $evidence = null,
        #[Assert\NotNull]
        public ?string $validUntil = null,
    ) {
    }
}
