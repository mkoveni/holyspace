<?php

declare(strict_types=1);

namespace App\Volunteering\Application;

use App\Volunteering\Application\Contract\ActivityStaffing;
use App\Volunteering\Application\Contract\RosterReader;
use App\Volunteering\Application\Contract\StaffingSummary;

/** Counts open duties, positions and qualified coverage; surplus in one duty never hides a gap in another. */
final readonly class ActivityStaffingSummary implements ActivityStaffing
{
    private const MAX_SHIFTS = 200;

    public function __construct(private RosterReader $roster, private VolunteerReadiness $readiness)
    {
    }

    public function summarize(string $sourceKind, string $sourceId): StaffingSummary
    {
        $all = $this->roster->sourceShifts($sourceKind, $sourceId, self::MAX_SHIFTS + 1);
        $shifts = $positions = $confirmed = $ready = $proposed = $gaps = 0;
        foreach (\array_slice($all, 0, self::MAX_SHIFTS) as $shift) {
            if ('OPEN' !== $shift->status) {
                continue;
            }
            ++$shifts;
            $positions += $shift->positions;
            $qualified = 0;
            foreach ($this->roster->assignments($shift->id) as $assignment) {
                if ('PROPOSED' === $assignment->status) {
                    ++$proposed;
                }
                if ('CONFIRMED' === $assignment->status) {
                    ++$confirmed;
                    if ($this->readiness->assess($assignment->volunteerId, $shift, $assignment->id)->ready) {
                        ++$qualified;
                    }
                }
            }
            $ready += $qualified;
            $gaps += max(0, $shift->positions - $qualified);
        }

        return new StaffingSummary($shifts, $positions, $confirmed, $ready, $proposed, $gaps, \count($all) <= self::MAX_SHIFTS);
    }
}
