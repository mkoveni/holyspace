<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

use DateTimeImmutable;

interface OccurrenceReader
{
    /**
     * Whether a scheduled occurrence of an exclusive venue overlaps the window.
     *
     * @param list<string> $exclude occurrence ids to ignore
     */
    public function venueClash(string $venueId, DateTimeImmutable $from, DateTimeImmutable $until, array $exclude): bool;

    /** Whether the event still has a scheduled occurrence that has not ended. */
    public function hasFutureScheduled(string $eventId, DateTimeImmutable $now): bool;

    public function find(string $id): ?OccurrenceView;

    /** The occurrence together with the event's title, type and archive state. */
    public function entry(string $id): ?CalendarEntry;

    /**
     * Occurrences overlapping the window, by start time.
     *
     * @return list<CalendarEntry>
     */
    public function calendar(?string $eventId, string $search, DateTimeImmutable $from, DateTimeImmutable $until, ?string $status, int $limit, int $offset): array;

    public function calendarCount(?string $eventId, string $search, DateTimeImmutable $from, DateTimeImmutable $until, ?string $status): int;
}
