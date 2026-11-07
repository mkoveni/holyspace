<?php

declare(strict_types=1);

namespace App\People\Application\Query\GetPersonHouseholds;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\HouseholdReader;
use App\People\Application\Contract\PersonReader;
use App\People\Application\MembershipView;
use App\People\Domain\PeopleException;

final readonly class GetPersonHouseholdsHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private PersonReader $persons,
        private HouseholdReader $homes,
    ) {
    }

    /** @return list<MembershipView> */
    public function handle(GetPersonHouseholdsQuery $query): array
    {
        $this->authorization->requirePermission('people.read');
        if (!$this->persons->exists($query->personId)) {
            throw PeopleException::missing('Person');
        }

        return $this->homes->memberships($query->personId);
    }
}
