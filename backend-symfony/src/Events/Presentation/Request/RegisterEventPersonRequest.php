<?php

declare(strict_types=1);

namespace App\Events\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterEventPersonRequest
{
    /** A null expectedVersion means a first registration; a person with an earlier record must send its version. */
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Uuid]
        public ?string $requestId = null,
        #[Assert\NotNull]
        #[Assert\Uuid]
        public ?string $personId = null,
        #[Assert\PositiveOrZero]
        public ?int $expectedVersion = null,
    ) {
    }
}
