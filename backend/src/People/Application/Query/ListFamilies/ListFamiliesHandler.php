<?php

declare(strict_types=1);

namespace App\People\Application\Query\ListFamilies;

use App\People\Application\DTO\FamilySummaryView;
use App\People\Application\ReadModel\PeopleReadRepository;

final readonly class ListFamiliesHandler
{
    public function __construct(private PeopleReadRepository $read)
    {
    }

    /** @return list<FamilySummaryView> */
    public function __invoke(ListFamiliesQuery $q): array
    {
        return array_map(FamilySummaryView::fromRow(...), $this->read->families());
    }
}
