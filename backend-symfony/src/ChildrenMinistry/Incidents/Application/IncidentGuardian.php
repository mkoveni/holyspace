<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Application;

final readonly class IncidentGuardian
{
    public function __construct(public string $authorityId, public string $guardianId, public string $name)
    {
    }
}
