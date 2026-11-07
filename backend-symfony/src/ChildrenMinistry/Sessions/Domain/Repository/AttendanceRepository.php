<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Domain\Repository;

use App\ChildrenMinistry\Sessions\Domain\Attendance;
use App\ChildrenMinistry\Sessions\Domain\AttendanceEvent;
use App\ChildrenMinistry\Sessions\Domain\AttendanceSegment;
use DateTimeImmutable;

/** Write side of attendance. Callers hold the child and session locks first. */
interface AttendanceRepository
{
    public function attendanceForUpdate(string $id): ?Attendance;

    public function insertAttendance(Attendance $attendance): void;

    /** Updates the row only if its stored version is one less than the attendance's; otherwise a 409. */
    public function updateAttendance(Attendance $attendance): void;

    public function insertAttendanceSegment(AttendanceSegment $segment): void;

    public function insertAttendanceEvent(AttendanceEvent $event): void;

    /** Ends the visit's open segment. */
    public function closeSegment(string $attendanceId, DateTimeImmutable $at): void;
}
