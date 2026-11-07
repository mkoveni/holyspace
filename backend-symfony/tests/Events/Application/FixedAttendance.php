<?php

declare(strict_types=1);

namespace App\Tests\Events\Application;

use App\Events\Application\Contract\AttendanceCounts;
use App\Events\Application\Contract\AttendanceReader;
use DateTimeImmutable;

/** In-memory test double: no database. */
final class FixedAttendance implements AttendanceReader
{
    /** @var array<string, int> */
    public array $attended = [];

    /** @var list<string> */
    public array $present = [];

    public function isPresent(string $registrationId): bool
    {
        return \in_array($registrationId, $this->present, true);
    }

    public function attended(string $occurrenceId): int
    {
        return $this->attended[$occurrenceId] ?? 0;
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
