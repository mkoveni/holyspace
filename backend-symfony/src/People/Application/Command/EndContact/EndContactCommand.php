<?php

declare(strict_types=1);

namespace App\People\Application\Command\EndContact;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class EndContactCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $personId,
        public int $expectedVersion,
        public string $contactId,
    ) {
    }
}
