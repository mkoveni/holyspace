<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\GetReconciliation;

use App\Giving\Application\Contract\ReconciliationDetail;
use App\Giving\Application\Contract\ReconciliationReader;
use App\Giving\Application\GivingAccess;
use App\Giving\Domain\GivingException;

final readonly class GetReconciliationHandler
{
    public function __construct(
        private GivingAccess $access,
        private ReconciliationReader $reader,
    ) {
    }

    public function handle(GetReconciliationQuery $query): ReconciliationDetail
    {
        $this->access->read();

        return $this->reader->detail($query->batchId) ?? throw GivingException::missing();
    }
}
