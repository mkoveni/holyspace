<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Reporting\Application;

use DateTimeImmutable;

/** One stretch of a child in one room. A transfer ends one visit and starts another. No codes, care, collector or reasons. */
final readonly class ReportVisit
{
    public function __construct(
        public string $id,
        public string $attendanceId,
        public string $childId,
        public string $childName,
        public string $sessionId,
        public string $programmeId,
        public string $programme,
        public string $ministryClass,
        public string $room,
        public string $location,
        public string $timeZone,
        public DateTimeImmutable $arrivedAt,
        public ?DateTimeImmutable $leftAt,
        public string $outcome,
        public bool $supervisorCollection,
    ) {
    }

    public function named(string $name): self
    {
        return new self($this->id, $this->attendanceId, $this->childId, $name, $this->sessionId, $this->programmeId, $this->programme, $this->ministryClass, $this->room, $this->location, $this->timeZone, $this->arrivedAt, $this->leftAt, $this->outcome, $this->supervisorCollection);
    }
}
