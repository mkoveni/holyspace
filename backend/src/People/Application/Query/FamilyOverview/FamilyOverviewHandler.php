<?php

declare(strict_types=1);

namespace App\People\Application\Query\FamilyOverview;

use App\People\Application\ReadModel\FamilyOverviewRepository;

final readonly class FamilyOverviewHandler
{
    public function __construct(private FamilyOverviewRepository $read)
    {
    }

    /** @return list<\App\People\Application\DTO\FamilyOverviewView> */
    public function __invoke(FamilyOverviewQuery $query): array
    {
        return $this->read->overview($query->familyId);
    }
}
