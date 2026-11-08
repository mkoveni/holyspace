<?php

declare(strict_types=1);

namespace App\Communities\Application;

use App\Communities\Domain\CommunityException;
use App\Events\Application\Contract\CalendarFailure;
use App\Events\Application\Contract\CalendarOccurrence;
use App\Events\Application\Contract\CalendarPreview;
use App\Events\Application\Contract\CommunityCalendar;
use App\Events\Application\Contract\CommunitySchedule;

/**
 * Events' published calendar as Communities uses it. A {@see CalendarFailure} becomes a {@see CommunityException} with
 * the same message and status, as Java's `CommunityExceptionHandler` answers it; a missing date is a missing record.
 */
final readonly class MeetingCalendar
{
    public function __construct(private CommunityCalendar $calendar)
    {
    }

    public static function schedule(string $venueId, string $timeZone, string $frequency, int $interval, int $count, string $firstDate, string $startsAt, string $endsAt, int $endDayOffset): CommunitySchedule
    {
        return self::translate(static fn (): CommunitySchedule => CommunitySchedule::of($venueId, $timeZone, $frequency, $interval, $count, $firstDate, $startsAt, $endsAt, $endDayOffset));
    }

    public function preview(CommunitySchedule $schedule): CalendarPreview
    {
        return self::translate(fn (): CalendarPreview => $this->calendar->preview($schedule));
    }

    /** @return list<CalendarOccurrence> */
    public function create(string $actorId, string $requestId, string $name, CommunitySchedule $schedule, string $reviewHash): array
    {
        return self::translate(fn (): array => $this->calendar->create($actorId, $requestId, $name, $schedule, $reviewHash));
    }

    /** Holds the occurrence row lock until the command's transaction ends. */
    public function lock(string $occurrenceId): CalendarOccurrence
    {
        return $this->calendar->lock($occurrenceId) ?? throw CommunityException::missing();
    }

    /**
     * @param list<string> $occurrenceIds
     *
     * @return array<string, CalendarOccurrence> keyed by occurrence id; every id must exist
     */
    public function dates(array $occurrenceIds): array
    {
        $dates = [];
        foreach ($this->calendar->dates($occurrenceIds) as $date) {
            $dates[(string) $date->id] = $date;
        }
        foreach ($occurrenceIds as $id) {
            if (!isset($dates[$id])) {
                throw CommunityException::missing();
            }
        }

        return $dates;
    }

    /**
     * @template T
     *
     * @param callable(): T $action
     *
     * @return T
     */
    private static function translate(callable $action): mixed
    {
        try {
            return $action();
        } catch (CalendarFailure $e) {
            throw CommunityException::refused($e->status, $e->getMessage());
        }
    }
}
