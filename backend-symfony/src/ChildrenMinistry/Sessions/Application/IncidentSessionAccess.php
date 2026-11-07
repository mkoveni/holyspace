<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Incidents\Application\Contract\IncidentSessionScope;

/** Staff may report incidents only on sessions where they are present; supervisors on any. */
final readonly class IncidentSessionAccess implements IncidentSessionScope
{
    public function __construct(private StaffScope $scope)
    {
    }

    public function require(string $actorId, string $sessionId): void
    {
        $this->scope->require($actorId, $sessionId);
    }
}
