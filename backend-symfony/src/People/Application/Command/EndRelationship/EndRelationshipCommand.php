<?php

declare(strict_types=1);

namespace App\People\Application\Command\EndRelationship;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class EndRelationshipCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $relationshipId,
        public int $expectedVersion,
    ) {
    }
}
