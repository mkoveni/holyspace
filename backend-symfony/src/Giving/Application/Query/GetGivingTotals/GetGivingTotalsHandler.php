<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\GetGivingTotals;

use App\Giving\Application\Contract\GivingReader;
use App\Giving\Application\Contract\GivingTotal;
use App\Giving\Application\GivingAccess;
use App\Giving\Application\GivingFilters;

final readonly class GetGivingTotalsHandler
{
    public function __construct(
        private GivingAccess $access,
        private GivingReader $reader,
    ) {
    }

    /** @return list<GivingTotal> */
    public function handle(GetGivingTotalsQuery $query): array
    {
        $this->access->read();
        GivingFilters::validate($query->filter);

        return $this->reader->totals($query->filter);
    }
}
