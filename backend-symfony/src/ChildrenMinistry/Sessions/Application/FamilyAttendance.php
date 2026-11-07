<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use DateTimeImmutable;

/** A visit as the family sees it: no collector, authorisation, operator, support or audit detail. */
final readonly class FamilyAttendance
{
    public function __construct(
        public string $id,
        public string $sessionId,
        public string $programme,
        public string $className,
        public string $room,
        public string $location,
        public string $timeZone,
        public DateTimeImmutable $arrivedAt,
        public ?DateTimeImmutable $collectedAt,
        public int $version,
        public bool $labelIssued,
    ) {
    }
}
