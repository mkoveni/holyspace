<?php

declare(strict_types=1);

namespace App\Volunteering\Application;

use App\Volunteering\Application\Contract\ServingActivity;
use App\Volunteering\Application\Contract\ShiftView;
use App\Volunteering\Domain\Shift;

/** Readiness and the activity registry read shifts as {@see ShiftView}; this adapts the aggregate. */
final class ShiftViews
{
    private function __construct()
    {
    }

    public static function of(Shift $shift): ShiftView
    {
        return new ShiftView(
            $shift->id,
            $shift->teamId,
            $shift->roleId,
            $shift->sourceKind,
            $shift->sourceId,
            $shift->sourceRevision,
            $shift->startsAt,
            $shift->endsAt,
            $shift->status,
            $shift->positions,
            $shift->requestId,
            $shift->requestHash,
            $shift->version,
            $shift->scheduleRevision,
        );
    }

    /** @return list<ServingActivity> the linked activity, or none for a standalone shift */
    public static function activity(ShiftView $shift): array
    {
        return null === $shift->sourceId ? [] : [new ServingActivity($shift->sourceKind, $shift->sourceId)];
    }
}
