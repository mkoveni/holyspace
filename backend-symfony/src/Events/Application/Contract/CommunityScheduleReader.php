<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

/** Read-only batch projection of linked community schedules. Does not schedule or lock occurrences. */
interface CommunityScheduleReader
{
    /**
     * @param list<string> $occurrenceIds
     *
     * @return list<CalendarOccurrence>
     */
    public function dates(array $occurrenceIds): array;
}
