<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Revokes a guardian authority or a pickup grant: the record, the version the caller saw and why. */
final readonly class RevokeRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $id = null,
        #[Assert\Length(max: 2000)]
        public ?string $reason = null,
    ) {
    }
}
