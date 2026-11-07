<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Contract;

use App\ChildrenMinistry\Sessions\Domain\Attendance;
use App\ChildrenMinistry\Sessions\Domain\AttendanceEvent;
use App\ChildrenMinistry\Sessions\Domain\AttendanceSegment;

/** Read side of attendance. */
interface AttendanceReader
{
    public function attendance(string $id): ?Attendance;

    /**
     * Visits currently in the session (or all of them), by arrival then id.
     *
     * @return list<Attendance>
     */
    public function roster(string $sessionId, bool $openOnly, int $limit, int $offset): array;

    /** Children checked in and not yet collected. */
    public function occupancy(string $sessionId): int;

    /** Whether the child is checked in to the session and not yet collected. */
    public function checkedIn(string $childId, string $sessionId): bool;

    /** The visit this staff member already created with this request id, for idempotent retries. */
    public function arrival(string $actorId, string $requestId): ?Attendance;

    /**
     * Every visit of the child, newest arrival first.
     *
     * @return list<Attendance>
     */
    public function childAttendance(string $childId, int $limit, int $offset): array;

    /** @return list<AttendanceEvent> oldest first */
    public function events(string $attendanceId, int $limit, int $offset): array;

    /** @return list<AttendanceSegment> oldest first */
    public function segments(string $attendanceId): array;
}
