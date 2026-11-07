<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterWorkerRequest
{
    public function __construct(
        #[Assert\NotNull, Assert\Uuid]
        public ?string $personId = null,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $accountId = null,
        #[Assert\NotNull]
        public ?string $screenedUntil = null,
        #[Assert\NotNull]
        public ?string $trainedUntil = null,
        #[Assert\Length(max: 2000)]
        public ?string $evidenceReference = null,
    ) {
    }
}
