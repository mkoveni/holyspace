<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateRoomRequest
{
    public function __construct(
        #[Assert\NotNull, Assert\Uuid]
        public ?string $locationId = null,
        public string $name = '',
        public int $capacity = 0,
    ) {
    }
}
