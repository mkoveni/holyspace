<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Readiness;

use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

final readonly class ReadinessEnrolment
{
    public function __construct(
        public string $id,
        public string $programmeId,
        public string $childId,
        public CalendarDate $validFrom,
        public CalendarDate $validUntil,
        public ?DateTimeImmutable $endedAt,
        public int $version,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
