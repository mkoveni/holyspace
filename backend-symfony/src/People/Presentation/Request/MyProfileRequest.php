<?php

declare(strict_types=1);

namespace App\People\Presentation\Request;

use App\People\Domain\ValueObject\Gender;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class MyProfileRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        public ?string $titleCode = null,
        public string $firstName = '',
        public string $lastName = '',
        #[Assert\NotNull]
        public ?Gender $gender = null,
    ) {
    }
}
