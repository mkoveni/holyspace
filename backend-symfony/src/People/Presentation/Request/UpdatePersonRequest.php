<?php

declare(strict_types=1);

namespace App\People\Presentation\Request;

use App\People\Domain\ValueObject\MembershipType;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdatePersonRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotNull, Assert\Valid]
        public ?PersonDataRequest $person = null,
        #[Assert\NotNull]
        public ?MembershipType $membershipType = null,
    ) {
    }
}
