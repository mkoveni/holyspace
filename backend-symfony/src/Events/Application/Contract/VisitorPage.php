<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

final readonly class VisitorPage
{
    /** @param list<VisitorRow> $visitors */
    public function __construct(public array $visitors, public int $total, public int $pending)
    {
    }
}
