<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

use DateTimeImmutable;

interface RosterReader
{
    public function assignment(string $id): ?AssignmentView;

    public function shift(string $id): ?ShiftView;

    /** @return list<ShiftView> shifts linked to a source activity, by start time, at most $limit */
    public function sourceShifts(string $kind, string $sourceId, int $limit): array;

    /** @return list<AssignmentView> assignments of a shift, active first, at most 1000 */
    public function assignments(string $shiftId): array;

    /**
     * Non-cancelled assignments on non-cancelled shifts overlapping the interval, at most 1001.
     *
     * @return list<AssignmentView>
     */
    public function volunteerAssignments(string $volunteerId, DateTimeImmutable $from, DateTimeImmutable $until): array;
}
