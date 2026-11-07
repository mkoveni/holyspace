<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

use DateTimeImmutable;

/**
 * Published readiness for one exact assignment, source and duty interval. Consumers never see Volunteering
 * aggregates; any failure to assess surfaces as {@see StaffingUnavailable}.
 */
interface StaffingDirectory
{
    /** @throws StaffingUnavailable when the assignment or its supporting records cannot be assessed */
    public function assess(
        string $assignmentId,
        string $sessionId,
        string $responsibility,
        DateTimeImmutable $from,
        DateTimeImmutable $until,
    ): StaffingAssessment;

    /**
     * Assessments for every non-cancelled assignment linked to the session.
     *
     * @return list<StaffingAssessment>
     *
     * @throws StaffingUnavailable when the session has more than 100 linked shifts or cannot be assessed
     */
    public function candidates(string $sessionId, DateTimeImmutable $from, DateTimeImmutable $until): array;
}
