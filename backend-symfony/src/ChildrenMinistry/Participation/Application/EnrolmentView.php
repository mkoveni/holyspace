<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application;

use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

final readonly class EnrolmentView
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

    public function covers(CalendarDate $on): bool
    {
        return null === $this->endedAt && !$on->isBefore($this->validFrom) && $on->isBefore($this->validUntil);
    }
}
