<?php

declare(strict_types=1);

namespace App\Tests\Events\Application;

use App\Events\Application\Contract\AttendanceCounts;
use App\Events\Application\Contract\AttendanceReader;
use App\Events\Domain\Attendance;
use App\Events\Domain\Repository\AttendanceRepository;
use DateTimeImmutable;

/** In-memory test double: no database. */
final class InMemoryAttendance implements AttendanceRepository, AttendanceReader
{
    /** @var array<string, Attendance> keyed by registration id */
    public array $rows = [];
    /** @var list<string> */
    public array $history = [];

    public function findByRegistration(string $registrationId): ?Attendance
    {
        return $this->rows[$registrationId] ?? null;
    }

    public function insert(Attendance $attendance): void
    {
        $this->rows[$attendance->registrationId] = $attendance;
    }

    public function save(Attendance $attendance): void
    {
        $this->rows[$attendance->registrationId] = $attendance;
    }

    public function recordHistory(string $attendanceId, string $actorId, string $action, string $reason, DateTimeImmutable $at): void
    {
        $this->history[] = "$action:$reason";
    }

    public function isPresent(string $registrationId): bool
    {
        return 'PRESENT' === ($this->rows[$registrationId]->status ?? null);
    }

    public function attended(string $occurrenceId): int
    {
        return \count(array_filter($this->rows, static fn (Attendance $a): bool => 'PRESENT' === $a->status));
    }

    public function roster(string $occurrenceId, ?array $personIds, ?string $status, DateTimeImmutable $now, int $limit, int $offset): array
    {
        return [];
    }

    public function rosterCount(string $occurrenceId, ?array $personIds, ?string $status, DateTimeImmutable $now): int
    {
        return 0;
    }

    public function counts(string $occurrenceId, DateTimeImmutable $now): AttendanceCounts
    {
        return new AttendanceCounts(0, 0, 0, $this->attended($occurrenceId), 0);
    }

    public function history(string $registrationId, int $limit, int $offset): ?array
    {
        return null;
    }
}
