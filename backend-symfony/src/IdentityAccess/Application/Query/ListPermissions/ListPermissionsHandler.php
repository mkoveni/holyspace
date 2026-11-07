<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\ListPermissions;

use App\IdentityAccess\Application\Authorization\Authorization;
use App\IdentityAccess\Application\Contract\AccessReader;
use App\IdentityAccess\Application\Contract\PermissionSummary;

final readonly class ListPermissionsHandler
{
    public function __construct(private AccessReader $access, private Authorization $authorization)
    {
    }

    /** @return list<PermissionSummary> */
    public function handle(ListPermissionsQuery $query): array
    {
        $this->authorization->requirePermission('identity.manage');

        return $this->access->permissions();
    }
}
