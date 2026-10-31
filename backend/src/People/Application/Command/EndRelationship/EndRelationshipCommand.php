<?php

declare(strict_types=1);

namespace App\People\Application\Command\EndRelationship;

use App\Shared\Application\CQRS\Command;

final readonly class EndRelationshipCommand implements Command
{
    public function __construct(public string $relationshipId, public string $endedAt)
    {
    }
}
