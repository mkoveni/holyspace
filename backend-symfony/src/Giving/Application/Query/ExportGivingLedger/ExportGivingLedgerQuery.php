<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\ExportGivingLedger;

use App\Giving\Application\Contract\GiftFilter;

final readonly class ExportGivingLedgerQuery
{
    public function __construct(public GiftFilter $filter)
    {
    }
}
