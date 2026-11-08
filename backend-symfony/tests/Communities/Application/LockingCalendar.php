<?php

declare(strict_types=1);

namespace App\Tests\Communities\Application;

use App\Events\Application\Contract\CalendarOccurrence;
use App\Events\Application\Contract\CalendarPreview;
use App\Events\Application\Contract\CommunityCalendar;
use App\Events\Application\Contract\CommunitySchedule;
use App\Tests\Events\Application\LockLog;
use LogicException;

/** Occurrences by id that record when one is locked. */
final class LockingCalendar implements CommunityCalendar
{
    /** @var array<string, CalendarOccurrence> */
    public array $dates = [];

    public function __construct(private readonly LockLog $log)
    {
    }

    public function preview(CommunitySchedule $schedule): CalendarPreview
    {
        throw new LogicException('not used');
    }

    public function create(string $actorId, string $requestId, string $name, CommunitySchedule $schedule, ?string $hash): array
    {
        throw new LogicException('not used');
    }

    public function get(string $occurrenceId): ?CalendarOccurrence
    {
        return $this->dates[$occurrenceId] ?? null;
    }

    public function dates(array $occurrenceIds): array
    {
        return array_values(array_intersect_key($this->dates, array_flip($occurrenceIds)));
    }

    public function lock(string $occurrenceId): ?CalendarOccurrence
    {
        $this->log->locks[] = 'occurrence:'.$occurrenceId;

        return $this->dates[$occurrenceId] ?? null;
    }
}
