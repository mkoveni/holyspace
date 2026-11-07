<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application\Command\CreateClass;

use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class CreateClassCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $programmeId,
        public string $name,
        public int $minAgeMonths,
        public int $maxAgeMonths,
    ) {
    }
}
