<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ReportIncidentRequest
{
    public function __construct(
        #[Assert\NotNull, Assert\Uuid]
        public ?string $requestId = null,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $childId = null,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $sessionId = null,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $segmentId = null,
        #[Assert\NotBlank]
        public ?string $category = null,
        #[Assert\NotBlank]
        public ?string $severity = null,
        #[Assert\NotNull]
        public ?string $occurredAt = null,
        #[Assert\NotBlank, Assert\Length(max: 5000)]
        public ?string $description = null,
        #[Assert\NotBlank, Assert\Length(max: 3000)]
        public ?string $immediateAction = null,
        #[Assert\NotNull]
        public ?string $reviewOn = null,
        #[Assert\NotNull]
        public ?string $retainUntil = null,
    ) {
    }

    public function __debugInfo(): array
    {
        return ['restricted' => true];
    }
}
