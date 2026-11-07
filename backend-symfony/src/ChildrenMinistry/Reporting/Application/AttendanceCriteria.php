<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Reporting\Application;

use DateTimeImmutable;

/** A validated filter: instants for the church-local days, and the children a name search matched. */
final readonly class AttendanceCriteria
{
    /** @param list<string>|null $matchingChildIds null for every child; an empty list matches none */
    public function __construct(
        public ?DateTimeImmutable $from,
        public ?DateTimeImmutable $until,
        public ?string $programmeId,
        public ?string $locationId,
        public ?string $childId,
        public ?array $matchingChildIds,
        public string $outcome,
        public ?string $attendanceId,
    ) {
    }
}
