<?php

declare(strict_types=1);

namespace App\People\Application\Query\GetHousehold;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\HouseholdReader;
use App\People\Application\HouseholdView;
use App\People\Domain\PeopleException;

final readonly class GetHouseholdHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private HouseholdReader $homes,
    ) {
    }

    public function handle(GetHouseholdQuery $query): HouseholdView
    {
        $this->authorization->requirePermission('people.read');

        return $this->homes->find($query->householdId) ?? throw PeopleException::missing('Household');
    }
}
