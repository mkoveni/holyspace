<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

final readonly class RosterPage
{
    /** @param list<RosterEntryView> $roster */
    public function __construct(public array $roster, public int $total, public AttendanceReport $counts)
    {
    }
}
