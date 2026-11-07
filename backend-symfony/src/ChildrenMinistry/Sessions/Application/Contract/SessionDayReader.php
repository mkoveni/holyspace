<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Contract;

use App\ChildrenMinistry\Sessions\Domain\Attendance;
use App\Shared\Domain\CalendarDate;

/** The queries behind the station's session-day view. A null `$matchingIds` means no search filter. */
interface SessionDayReader
{
    /**
     * Children placed in the class whose placement and enrolment cover the day.
     *
     * @param list<string>|null $matchingIds
     *
     * @return array{childIds: list<string>, total: int} one page ordered by child id
     */
    public function expected(string $classId, CalendarDate $on, ?array $matchingIds, int $limit, int $offset): array;

    /**
     * @param list<string>|null $matchingIds
     *
     * @return array{attendance: list<Attendance>, total: int} still present, or collected, newest arrival first
     */
    public function attendance(string $sessionId, bool $collected, ?array $matchingIds, int $limit, int $offset): array;

    /**
     * The open visits of these children in the session.
     *
     * @param list<string> $childIds
     *
     * @return array<string, Attendance> keyed by child id
     */
    public function current(string $sessionId, array $childIds): array;
}
