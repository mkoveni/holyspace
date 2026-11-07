<?php

declare(strict_types=1);

namespace App\People\Application\Command\EndHouseholdMembership;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class EndHouseholdMembershipCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $householdId,
        public int $expectedVersion,
        public string $membershipId,
    ) {
    }
}
