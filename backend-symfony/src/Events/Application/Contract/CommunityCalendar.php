<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

/**
 * Lets a community schedule its meetings as event occurrences without touching Events tables or aggregates.
 * Callers run inside their own transaction; every method signals refusals with {@see CalendarFailure}.
 */
interface CommunityCalendar
{
    /** @throws CalendarFailure */
    public function preview(CommunitySchedule $schedule): CalendarPreview;

    /**
     * Creates a meeting event with the reviewed schedule. Repeating a request id with the same details returns the same dates.
     *
     * @return list<CalendarOccurrence>
     *
     * @throws CalendarFailure
     */
    public function create(string $actorId, string $requestId, string $name, CommunitySchedule $schedule, ?string $hash): array;

    public function get(string $occurrenceId): ?CalendarOccurrence;

    /**
     * @param list<string> $occurrenceIds
     *
     * @return list<CalendarOccurrence> the ones that exist
     */
    public function dates(array $occurrenceIds): array;

    /** Like get, but holds a row lock on the occurrence until the surrounding transaction ends. */
    public function lock(string $occurrenceId): ?CalendarOccurrence;
}
