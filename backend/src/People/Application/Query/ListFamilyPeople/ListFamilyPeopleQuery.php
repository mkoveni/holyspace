<?php

declare(strict_types=1);

namespace App\People\Application\Query\ListFamilyPeople;

use App\Shared\Application\CQRS\Query;

final readonly class ListFamilyPeopleQuery implements Query
{
    public function __construct(public string $familyId)
    {
    }
}
