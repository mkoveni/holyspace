<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

use DateTimeImmutable;

interface AttendanceReader
{
    /** Whether the registration has a PRESENT attendance record. */
    public function isPresent(string $registrationId): bool;

    /** People recorded present for the occurrence. */
    public function attended(string $occurrenceId): int;

    /**
     * The occurrence's registrations in queue order, each with its attendance and roster state
     * (PRESENT, WAITLISTED, CANCELLED, ABSENT once a scheduled occurrence has ended, otherwise EXPECTED).
     *
     * @param list<string>|null $personIds restrict to these people; null means everyone
     *
     * @return list<RosterRow>
     */
    public function roster(string $occurrenceId, ?array $personIds, ?string $status, DateTimeImmutable $now, int $limit, int $offset): array;

    /** @param list<string>|null $personIds */
    public function rosterCount(string $occurrenceId, ?array $personIds, ?string $status, DateTimeImmutable $now): int;

    public function counts(string $occurrenceId, DateTimeImmutable $now): AttendanceCounts;

    /** @return list<AttendanceHistoryEntry>|null newest first; null when the registration has no attendance record */
    public function history(string $registrationId, int $limit, int $offset): ?array;
}
