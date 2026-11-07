<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class QueueEmailRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $verificationId = null,
        #[Assert\NotBlank]
        public ?string $reviewHash = null,
        public ?string $scheduledAt = null,
    ) {
    }
}
