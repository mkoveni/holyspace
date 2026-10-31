<?php

declare(strict_types=1);

namespace App\People\Application\Query\ListPersonFamilies;

use App\People\Application\DTO\PersonFamilyView;
use App\People\Application\ReadModel\PeopleReadRepository;

final readonly class ListPersonFamiliesHandler
{
    public function __construct(private PeopleReadRepository $read)
    {
    }

    /** @return list<PersonFamilyView> */
    public function __invoke(ListPersonFamiliesQuery $q): array
    {
        return array_map(PersonFamilyView::fromRow(...), $this->read->familiesForPerson($q->personId));
    }
}
