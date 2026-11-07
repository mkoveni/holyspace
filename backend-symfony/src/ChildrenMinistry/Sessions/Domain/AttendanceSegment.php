<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Domain;

use DateTimeImmutable;

/** The part of a visit spent in one session, with the evidence that admitted the child to it. */
final readonly class AttendanceSegment
{
    public function __construct(
        public string $id,
        public string $attendanceId,
        public string $sessionId,
        public string $enrolmentId,
        public string $assignmentId,
        public string $consentId,
        public DateTimeImmutable $arrivedAt,
        public ?DateTimeImmutable $leftAt,
    ) {
    }
}
