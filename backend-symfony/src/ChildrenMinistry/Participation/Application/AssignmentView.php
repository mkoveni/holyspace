<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application;

use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

final readonly class AssignmentView
{
    public function __construct(
        public string $id,
        public string $programmeId,
        public string $classId,
        public string $childId,
        public string $enrolmentId,
        public CalendarDate $validFrom,
        public CalendarDate $validUntil,
        public ?DateTimeImmutable $endedAt,
        public int $version,
    ) {
    }
}
