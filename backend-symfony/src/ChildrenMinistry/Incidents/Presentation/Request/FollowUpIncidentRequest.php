<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class FollowUpIncidentRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotBlank]
        public ?string $action = null,
        #[Assert\Uuid]
        public ?string $ownerId = null,
        public ?string $reviewOn = null,
        #[Assert\Uuid]
        public ?string $guardianAuthorityId = null,
        #[Assert\NotBlank, Assert\Length(max: 3000)]
        public ?string $note = null,
    ) {
    }

    public function __debugInfo(): array
    {
        return ['restricted' => true];
    }
}
