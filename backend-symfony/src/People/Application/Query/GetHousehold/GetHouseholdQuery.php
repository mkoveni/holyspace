<?php

declare(strict_types=1);

namespace App\People\Application\Query\GetHousehold;

final readonly class GetHouseholdQuery
{
    public function __construct(
        public string $householdId,
    ) {
    }
}
