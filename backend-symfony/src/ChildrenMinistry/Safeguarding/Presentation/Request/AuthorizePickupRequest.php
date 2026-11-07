<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AuthorizePickupRequest
{
    public function __construct(
        #[Assert\NotNull, Assert\Uuid]
        public ?string $programmeId = null,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $childId = null,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $collectorId = null,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $authorityId = null,
        #[Assert\NotNull]
        public ?string $validFrom = null,
        #[Assert\NotNull]
        public ?string $validUntil = null,
        #[Assert\Length(max: 2000)]
        public ?string $evidenceReference = null,
    ) {
    }
}
