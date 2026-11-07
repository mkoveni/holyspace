<?php

declare(strict_types=1);

namespace App\People\Application\Command\UpdatePerson;

use App\People\Domain\ValueObject\MembershipType;
use App\People\Domain\ValueObject\PersonDetails;
use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class UpdatePersonCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $personId,
        public int $expectedVersion,
        public PersonDetails $details,
        public MembershipType $membershipType,
    ) {
    }
}
