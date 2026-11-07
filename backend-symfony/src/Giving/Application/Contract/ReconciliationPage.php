<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

final readonly class ReconciliationPage
{
    /** @param list<ReconciliationSummary> $items */
    public function __construct(public array $items, public int $total)
    {
    }
}
