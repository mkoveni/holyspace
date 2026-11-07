<?php

declare(strict_types=1);

namespace App\Events\Domain\Repository;

use App\Events\Domain\Attendance;
use DateTimeImmutable;

interface AttendanceRepository
{
    public function findByRegistration(string $registrationId): ?Attendance;

    public function insert(Attendance $attendance): void;

    /** Saves the next version. Throws a conflict when the stored version is not the previous one. */
    public function save(Attendance $attendance): void;

    public function recordHistory(string $attendanceId, string $actorId, string $action, string $reason, DateTimeImmutable $at): void;
}
