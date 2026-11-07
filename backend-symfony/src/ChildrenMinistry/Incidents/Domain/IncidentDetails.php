<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Domain;

use App\ChildrenMinistry\Shared\Domain\ChildrenRules;

/** The private account of what happened and what was done. Never logged, never in ordinary audit rows. */
final readonly class IncidentDetails
{
    public string $description;

    public string $immediateAction;

    public function __construct(?string $description, ?string $immediateAction)
    {
        $this->description = ChildrenRules::text($description, 5000);
        $this->immediateAction = ChildrenRules::text($immediateAction, 3000);
    }

    public function __debugInfo(): array
    {
        return ['restricted' => true];
    }
}
