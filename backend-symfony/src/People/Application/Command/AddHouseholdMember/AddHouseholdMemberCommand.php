<?php

declare(strict_types=1);

namespace App\People\Application\Command\AddHouseholdMember;

use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class AddHouseholdMemberCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $householdId,
        public int $expectedVersion,
        public string $personId,
        public bool $primary,
    ) {
    }
}
