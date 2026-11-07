<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

final readonly class RosterPage
{
    /** @param list<RosterEntry> $attendees */
    public function __construct(public array $attendees, public int $total, public AttendanceCounts $counts, public bool $admissionOpen)
    {
    }
}
