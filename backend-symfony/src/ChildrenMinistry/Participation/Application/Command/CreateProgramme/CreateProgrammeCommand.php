<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application\Command\CreateProgramme;

use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class CreateProgrammeCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $name,
        public int $minStaff,
        public int $childrenPerStaff,
    ) {
    }
}
