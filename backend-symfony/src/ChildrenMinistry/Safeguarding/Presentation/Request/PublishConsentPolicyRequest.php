<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PublishConsentPolicyRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $programmeId = null,
        #[Assert\Length(max: 2000)]
        public ?string $label = null,
        #[Assert\Length(max: 2000)]
        public ?string $documentReference = null,
        #[Assert\Length(max: 2000)]
        public ?string $contentHash = null,
    ) {
    }
}
