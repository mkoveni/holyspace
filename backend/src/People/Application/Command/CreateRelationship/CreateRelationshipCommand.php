<?php

declare(strict_types=1);

namespace App\People\Application\Command\CreateRelationship;

use App\Shared\Application\CQRS\Command;

final readonly class CreateRelationshipCommand implements Command
{
    public function __construct(
        public string $personId,
        public string $relatedPersonId,
        public string $type,
    ) {
    }
}
