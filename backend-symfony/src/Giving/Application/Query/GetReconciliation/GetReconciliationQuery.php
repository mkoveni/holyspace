<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\GetReconciliation;

final readonly class GetReconciliationQuery
{
    public function __construct(public string $batchId)
    {
    }
}
