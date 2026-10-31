<?php

declare(strict_types=1);

namespace App\People\Application\Query\ListFamilyPeople;

use App\People\Application\DTO\FamilyMemberView;
use App\People\Application\ReadModel\PeopleReadRepository;

final readonly class ListFamilyPeopleHandler
{
    public function __construct(private PeopleReadRepository $read)
    {
    }

    /** @return list<FamilyMemberView> */
    public function __invoke(ListFamilyPeopleQuery $q): array
    {
        return array_map(FamilyMemberView::fromRow(...), $this->read->peopleInFamily($q->familyId));
    }
}
