<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\ListRoles;

use App\IdentityAccess\Application\Authorization\Authorization;
use App\IdentityAccess\Application\Contract\AccessReader;
use App\IdentityAccess\Application\Contract\RoleSummary;

final readonly class ListRolesHandler
{
    public function __construct(private AccessReader $access, private Authorization $authorization)
    {
    }

    /** @return list<RoleSummary> */
    public function handle(ListRolesQuery $query): array
    {
        $this->authorization->requirePermission('identity.manage');

        return $this->access->roles();
    }
}
