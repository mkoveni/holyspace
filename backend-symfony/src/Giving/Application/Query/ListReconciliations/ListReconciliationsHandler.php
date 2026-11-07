<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\ListReconciliations;

use App\Giving\Application\Contract\ReconciliationPage;
use App\Giving\Application\Contract\ReconciliationReader;
use App\Giving\Application\GivingAccess;
use App\Giving\Domain\GivingRules;

final readonly class ListReconciliationsHandler
{
    public function __construct(
        private GivingAccess $access,
        private ReconciliationReader $reader,
    ) {
    }

    public function handle(ListReconciliationsQuery $query): ReconciliationPage
    {
        $this->access->read();
        GivingRules::page($query->limit, $query->offset);

        return $this->reader->list($query->limit, $query->offset);
    }
}
