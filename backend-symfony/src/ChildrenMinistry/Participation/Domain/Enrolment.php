<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Domain;

use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

/** A child's enrolment in a programme over a half-open date range [validFrom, validUntil). Ending it never erases it. */
final readonly class Enrolment
{
    private function __construct(
        public string $id,
        public string $programmeId,
        public string $childId,
        public CalendarDate $validFrom,
        public CalendarDate $validUntil,
        public ?DateTimeImmutable $endedAt,
        public int $version,
        public DateTimeImmutable $createdAt,
    ) {
        ChildrenRules::period($validFrom, $validUntil);
    }

    public static function create(
        string $id,
        string $programmeId,
        string $childId,
        CalendarDate $validFrom,
        CalendarDate $validUntil,
        DateTimeImmutable $now,
    ): self {
        return new self($id, $programmeId, $childId, $validFrom, $validUntil, null, 0, $now);
    }

    public static function reconstitute(
        string $id,
        string $programmeId,
        string $childId,
        CalendarDate $validFrom,
        CalendarDate $validUntil,
        ?DateTimeImmutable $endedAt,
        int $version,
        DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $programmeId, $childId, $validFrom, $validUntil, $endedAt, $version, $createdAt);
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

        return new self($this->id, $this->programmeId, $this->childId, $this->validFrom, $this->validUntil, $now, $this->version + 1, $this->createdAt);
    }
}
