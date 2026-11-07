<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application\Command\CreateLocation;

use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class CreateLocationCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $name,
        public string $timeZone,
    ) {
    }
}
