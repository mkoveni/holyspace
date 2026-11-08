<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Query\GetMyVolunteering;

use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Volunteering\Application\Contract\MemberOverview;
use App\Volunteering\Application\MemberVolunteerViews;
use App\Volunteering\Application\ServingPermissions;

/**
 * Java `GetMyVolunteeringHandler`: any signed-in member may ask; without live volunteering.serve the answer is an
 * empty, not-permitted overview.
 */
final readonly class GetMyVolunteeringHandler
{
    public function __construct(
        private AuthenticatedAccount $identity,
        private PermissionChecker $auth,
        private MemberVolunteerViews $views,
    ) {
    }

    public function handle(GetMyVolunteeringQuery $query): MemberOverview
    {
        $accountId = $this->identity->requireAccount();

        return $this->views->overview($accountId, $this->auth->hasPermission(ServingPermissions::SERVE), $query->limit, $query->offset);
    }
}
