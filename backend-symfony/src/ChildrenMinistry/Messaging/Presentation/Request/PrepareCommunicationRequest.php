<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PrepareCommunicationRequest
{
    public function __construct(
        #[Assert\NotNull, Assert\Uuid]
        public ?string $requestId = null,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $childId = null,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $authorityId = null,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $programmeId = null,
        #[Assert\Uuid]
        public ?string $sessionId = null,
        #[Assert\NotBlank]
        public ?string $type = null,
        #[Assert\NotNull]
        public ?string $dueOn = null,
    ) {
    }
}
