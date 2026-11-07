<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

final readonly class GiftPage
{
    /** @param list<GiftSummary> $items */
    public function __construct(public array $items, public int $total)
    {
    }
}
