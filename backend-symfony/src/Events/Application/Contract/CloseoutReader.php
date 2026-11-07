<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

interface CloseoutReader
{
    public function closeout(string $occurrenceId): ?CloseoutView;

    /** @return list<CloseoutHistoryEntry> newest version first, with empty actor names */
    public function history(string $occurrenceId, int $limit, int $offset): array;

    /** @return list<NoticeRef> by id */
    public function notices(string $occurrenceId, int $limit): array;

    /** Confirmed bookings that have a reminder approved for the current registration, schedule and event versions. */
    public function currentReminderBookings(string $occurrenceId): int;

    /** A sum that moves whenever any attendance record of the occurrence is created or corrected. */
    public function attendanceRevision(string $occurrenceId): int;
}
