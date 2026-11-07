<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Contract;

/** How prepared a child is for a programme: completed steps out of the total. Never carries care or guardian evidence. */
final readonly class ReadinessSummary
{
    public function __construct(public bool $ready, public int $completed, public int $total)
    {
    }
}
