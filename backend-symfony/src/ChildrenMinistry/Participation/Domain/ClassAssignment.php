<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Domain;

use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

/** Placement of an enrolled child in one class for a date range inside their enrolment. */
final readonly class ClassAssignment
{
    private function __construct(
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
        ChildrenRules::period($validFrom, $validUntil);
    }

    public static function create(
        string $id,
        string $programmeId,
        string $classId,
        string $childId,
        string $enrolmentId,
        CalendarDate $validFrom,
        CalendarDate $validUntil,
    ): self {
        return new self($id, $programmeId, $classId, $childId, $enrolmentId, $validFrom, $validUntil, null, 0);
    }

    public static function reconstitute(
        string $id,
        string $programmeId,
        string $classId,
        string $childId,
        string $enrolmentId,
        CalendarDate $validFrom,
        CalendarDate $validUntil,
        ?DateTimeImmutable $endedAt,
        int $version,
    ): self {
        return new self($id, $programmeId, $classId, $childId, $enrolmentId, $validFrom, $validUntil, $endedAt, $version);
    }

    public function covers(CalendarDate $on): bool
    {
        return null === $this->endedAt && !$on->isBefore($this->validFrom) && $on->isBefore($this->validUntil);
    }

    public function end(int $expectedVersion, DateTimeImmutable $now): self
    {
        ChildrenRules::version($this->version, $expectedVersion);
        if (null !== $this->endedAt) {
            throw ChildrenException::conflict('This participation already ended.');
        }

        return new self($this->id, $this->programmeId, $this->classId, $this->childId, $this->enrolmentId, $this->validFrom, $this->validUntil, $now, $this->version + 1);
    }
}
