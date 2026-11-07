<?php

declare(strict_types=1);

namespace App\IdentityAccess\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class MemberLinkRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $personId = null,
        public bool $active = false,
        public string $evidence = '',
    ) {
    }
}
