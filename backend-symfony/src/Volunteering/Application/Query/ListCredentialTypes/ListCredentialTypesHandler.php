<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Query\ListCredentialTypes;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Volunteering\Application\Contract\CredentialTypeView;
use App\Volunteering\Application\Contract\VolunteerReader;
use App\Volunteering\Application\ServingPermissions;

final readonly class ListCredentialTypesHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private VolunteerReader $volunteers,
    ) {
    }

    /** @return list<CredentialTypeView> */
    public function handle(ListCredentialTypesQuery $query): array
    {
        $this->auth->requirePermission(ServingPermissions::READ);

        return $this->volunteers->credentialTypes();
    }
}
