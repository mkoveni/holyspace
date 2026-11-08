<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\ListGivingFunds;

use App\Giving\Application\Contract\FundPage;
use App\Giving\Application\Contract\GivingReader;
use App\Giving\Application\GivingAccess;
use App\Giving\Domain\GivingRules;
use App\Shared\Domain\JavaString;

final readonly class ListGivingFundsHandler
{
    public function __construct(
        private GivingAccess $access,
        private GivingReader $reader,
    ) {
    }

    public function handle(ListGivingFundsQuery $query): FundPage
    {
        $this->access->read();
        GivingRules::page($query->limit, $query->offset);
        $search = null === $query->search || JavaString::isBlank($query->search) ? '' : JavaString::trim($query->search);

        return $this->reader->funds($query->archived, $search, $query->limit, $query->offset);
    }
}
