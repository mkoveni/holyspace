<?php

declare(strict_types=1);

namespace App\People\Application\Command\RegisterPerson;

use App\People\Domain\ValueObject\MembershipType;
use App\People\Domain\ValueObject\PersonDetails;
use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class RegisterPersonCommand implements Command
{
    public function __construct(
        public string $actorId,
        public PersonDetails $details,
        public ?MembershipType $membershipType,
    ) {
    }
}
