<?php

declare(strict_types=1);

namespace App\People\Application\Query\SearchHouseholds;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\HouseholdReader;
use App\People\Application\HouseholdSummary;
use App\People\Domain\PeopleException;
use App\People\Domain\PeopleRules;

final readonly class SearchHouseholdsHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private HouseholdReader $homes,
    ) {
    }

    /** @return list<HouseholdSummary> */
    public function handle(SearchHouseholdsQuery $query): array
    {
        $this->authorization->requirePermission('people.read');
        if ($query->limit < 1 || $query->limit > 100 || $query->offset < 0 || $query->offset > 1000000) {
            throw PeopleException::invalid('Invalid paging parameters.');
        }

        return $this->homes->search(PeopleRules::optionalText($query->search, 'Search', 200), $query->limit, $query->offset);
    }
}
