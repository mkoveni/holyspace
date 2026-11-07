<?php

declare(strict_types=1);

namespace App\People\Application\Command\CreateRelationship;

use App\People\Domain\ValueObject\RelationshipType;
use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class CreateRelationshipCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $sourceId,
        public string $targetId,
        public RelationshipType $type,
    ) {
    }
}
