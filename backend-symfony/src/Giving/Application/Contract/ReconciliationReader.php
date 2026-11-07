<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

/** Read side of reconciliation batches (Java `ReconciliationReader`). Reading never creates evidence. */
interface ReconciliationReader
{
    /** Batches newest reconciliation date first, then by id. */
    public function list(int $limit, int $offset): ReconciliationPage;

    public function detail(string $id): ?ReconciliationDetail;
}
