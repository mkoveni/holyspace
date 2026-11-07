<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Application\Query\ListIncidentOwners;

use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\IdentityAccess\Application\Contract\ResponsibleAccount;
use App\IdentityAccess\Application\Contract\ResponsibleAccountDirectory;

/** Active accounts that can take responsibility for an incident. */
final readonly class ListIncidentOwnersHandler
{
    public function __construct(private PermissionChecker $authorization, private ResponsibleAccountDirectory $accounts)
    {
    }

    /** @return list<ResponsibleAccount> */
    public function handle(ListIncidentOwnersQuery $query): array
    {
        $this->authorization->requirePermission('children.incidents.manage');

        return $this->accounts->search('children.incidents.manage', ChildrenRules::optional($query->search, 120));
    }
}
