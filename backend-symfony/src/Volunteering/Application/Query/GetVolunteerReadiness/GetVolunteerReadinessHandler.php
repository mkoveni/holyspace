<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Query\GetVolunteerReadiness;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Volunteering\Application\Contract\RosterReader;
use App\Volunteering\Application\Readiness;
use App\Volunteering\Application\ServingPermissions;
use App\Volunteering\Application\VolunteerReadiness;
use App\Volunteering\Domain\VolunteerException;

final readonly class GetVolunteerReadinessHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private RosterReader $roster,
        private VolunteerReadiness $readiness,
    ) {
    }

    public function handle(GetVolunteerReadinessQuery $query): Readiness
    {
        $this->auth->requirePermission(ServingPermissions::READ);
        $shift = $this->roster->shift($query->shiftId) ?? throw VolunteerException::missing('Shift');

        return $this->readiness->assess($query->volunteerId, $shift, null);
    }
}
