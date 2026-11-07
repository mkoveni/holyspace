<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RecordContactRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotBlank]
        public ?string $status = null,
        #[Assert\NotBlank, Assert\Length(max: 500)]
        public ?string $reference = null,
    ) {
    }
}
