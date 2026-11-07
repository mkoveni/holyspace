<?php

declare(strict_types=1);

namespace App\People\Application\Command\SetPersonArchived;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class SetPersonArchivedCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $personId,
        public int $expectedVersion,
        public bool $archived,
    ) {
    }
}
