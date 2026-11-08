<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

final readonly class MeetingPage
{
    /** @param list<MeetingSummary> $meetings */
    public function __construct(public array $meetings, public int $total)
    {
    }
}
