<?php

declare(strict_types=1);

namespace App\People\Application\Query\GetPersonHouseholds;

final readonly class GetPersonHouseholdsQuery
{
    public function __construct(
        public string $personId,
    ) {
    }
}
