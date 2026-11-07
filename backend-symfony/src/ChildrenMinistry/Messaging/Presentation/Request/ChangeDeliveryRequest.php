<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ChangeDeliveryRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotBlank, Assert\Length(max: 500)]
        public ?string $reason = null,
    ) {
    }
}
