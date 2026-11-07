<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Sessions\Domain\WeeklyRecurrence;
use App\ChildrenMinistry\Shared\Application\RequestFingerprint;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\Shared\Domain\CalendarDate;

/** What the planner asked for: a name, the session to copy at that version, and the weekly pattern. */
final readonly class SessionSeriesDraft
{
    public function __construct(public string $name, public string $sourceSessionId, public int $sourceVersion, public WeeklyRecurrence $recurrence)
    {
    }

    /** Identifies the request so a retry with the same id but a changed plan is recognised. */
    public function fingerprint(): string
    {
        return RequestFingerprint::of(
            ChildrenRules::text($this->name, 120),
            $this->sourceSessionId,
            $this->sourceVersion,
            $this->recurrence->firstDate->iso(),
            $this->recurrence->startsAt,
            $this->recurrence->endsAt,
            $this->recurrence->intervalWeeks,
            $this->recurrence->occurrences,
            implode(',', array_map(static fn (CalendarDate $date): string => $date->iso(), $this->recurrence->excludedDates)),
        );
    }
}
