<?php

declare(strict_types=1);

namespace App\People\Application\Command\CreateHousehold;

use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class CreateHouseholdCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $name,
    ) {
    }
}
