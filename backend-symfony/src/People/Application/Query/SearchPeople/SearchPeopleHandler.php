<?php

declare(strict_types=1);

namespace App\People\Application\Query\SearchPeople;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PersonReader;
use App\People\Application\PersonSummary;
use App\People\Domain\PeopleException;
use App\People\Domain\PeopleRules;
use App\Shared\Application\ChurchCalendar;

final readonly class SearchPeopleHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private PersonReader $persons,
        private ChurchCalendar $calendar,
    ) {
    }

    /** @return list<PersonSummary> */
    public function handle(SearchPeopleQuery $query): array
    {
        $this->authorization->requirePermission('people.read');
        if ($query->limit < 1 || $query->limit > 100 || $query->offset < 0 || $query->offset > 1000000) {
            throw PeopleException::invalid('Invalid paging parameters.');
        }
        $search = PeopleRules::optionalText($query->search, 'Search', 200);
        $asOf = $query->asOf ?? $this->calendar->today();

        return $this->persons->search($search, $query->includeArchived, $query->membershipType, $query->ageCategory, $asOf, $query->limit, $query->offset);
    }
}
