<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ReviewWorkerRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $personId = null,
        #[Assert\NotNull]
        public ?string $screenedUntil = null,
        #[Assert\NotNull]
        public ?string $trainedUntil = null,
        #[Assert\Length(max: 2000)]
        public ?string $evidenceReference = null,
        public bool $revoked = false,
    ) {
    }
}
