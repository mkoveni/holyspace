<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AssignClassRequest
{
    public function __construct(
        #[Assert\NotNull, Assert\Uuid]
        public ?string $enrolmentId = null,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $classId = null,
        public string $validFrom = '',
        public string $validUntil = '',
    ) {
    }
}
