<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateSessionRequest
{
    public function __construct(
        #[Assert\NotNull, Assert\Uuid]
        public ?string $classId = null,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $roomId = null,
        #[Assert\NotNull]
        public ?string $startsAt = null,
        #[Assert\NotNull]
        public ?string $endsAt = null,
        #[Assert\Range(min: 1, max: 10000)]
        public int $seatLimit = 0,
    ) {
    }
}
