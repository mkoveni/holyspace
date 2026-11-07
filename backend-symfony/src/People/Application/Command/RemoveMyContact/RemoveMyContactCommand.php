<?php

declare(strict_types=1);

namespace App\People\Application\Command\RemoveMyContact;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class RemoveMyContactCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $contactId,
        public int $expectedVersion,
    ) {
    }
}
