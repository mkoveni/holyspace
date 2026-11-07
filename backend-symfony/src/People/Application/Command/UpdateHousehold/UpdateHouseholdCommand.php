<?php

declare(strict_types=1);

namespace App\People\Application\Command\UpdateHousehold;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class UpdateHouseholdCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $householdId,
        public int $expectedVersion,
        public string $name,
    ) {
    }
}
