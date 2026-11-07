<?php

declare(strict_types=1);

namespace App\People\Presentation\Request;

use App\People\Domain\ValueObject\MembershipType;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterPersonRequest
{
    public function __construct(
        #[Assert\NotNull, Assert\Valid]
        public ?PersonDataRequest $person = null,
        public ?MembershipType $membershipType = null,
    ) {
    }
}
