<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Domain;

use DateTimeImmutable;

/** An append-only entry in a visit's history: ARRIVED, LABEL_ISSUED, TRANSFERRED, COLLECTED, SUPERVISOR_COLLECTION. */
final readonly class AttendanceEvent
{
    public function __construct(
        public string $id,
        public string $attendanceId,
        public string $actorId,
        public string $type,
        public string $reason,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
