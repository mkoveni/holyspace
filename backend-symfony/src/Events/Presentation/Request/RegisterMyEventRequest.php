<?php

declare(strict_types=1);

namespace App\Events\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterMyEventRequest
{
    /** A null expectedVersion is a first booking; someone with an earlier record sends its version. */
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Uuid]
        public ?string $requestId = null,
        #[Assert\PositiveOrZero]
        public ?int $expectedVersion = null,
    ) {
    }
}
