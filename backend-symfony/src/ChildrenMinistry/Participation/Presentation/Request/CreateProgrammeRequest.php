<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Presentation\Request;

final readonly class CreateProgrammeRequest
{
    public function __construct(
        public string $name = '',
        public int $minStaff = 0,
        public int $childrenPerStaff = 0,
    ) {
    }
}
