<?php

declare(strict_types=1);

namespace App\People\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RenameHouseholdRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        public string $name = '',
    ) {
    }
}
