<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Domain;

use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use DateTimeImmutable;

/** A recurring weekly plan and the sessions generated from it. Stopping is final. */
final readonly class SessionSeries
{
    private function __construct(
        public string $id,
        public string $name,
        public string $sourceSessionId,
        public string $classId,
        public string $roomId,
        public int $seatLimit,
        public string $timeZone,
        public WeeklyRecurrence $recurrence,
        public DateTimeImmutable $createdAt,
        public string $createdBy,
        public string $requestId,
        public string $requestFingerprint,
        public bool $stopped,
        public int $version,
    ) {
    }

    public static function create(string $id, string $name, MinistrySession $source, string $timeZone, WeeklyRecurrence $recurrence, DateTimeImmutable $createdAt, string $createdBy, string $requestId, string $requestFingerprint): self
    {
        return new self($id, ChildrenRules::text($name, 120), $source->id, $source->classId, $source->roomId, $source->seatLimit, $timeZone, $recurrence, $createdAt, $createdBy, $requestId, $requestFingerprint, false, 0);
    }

    public static function reconstitute(string $id, string $name, string $sourceSessionId, string $classId, string $roomId, int $seatLimit, string $timeZone, WeeklyRecurrence $recurrence, DateTimeImmutable $createdAt, string $createdBy, string $requestId, string $requestFingerprint, bool $stopped, int $version): self
    {
        return new self($id, $name, $sourceSessionId, $classId, $roomId, $seatLimit, $timeZone, $recurrence, $createdAt, $createdBy, $requestId, $requestFingerprint, $stopped, $version);
    }

    public function stop(int $expectedVersion): self
    {
        ChildrenRules::version($this->version, $expectedVersion);
        if ($this->stopped) {
            throw ChildrenException::conflict('Remaining planned dates have already been cancelled.');
        }

        return new self($this->id, $this->name, $this->sourceSessionId, $this->classId, $this->roomId, $this->seatLimit, $this->timeZone, $this->recurrence, $this->createdAt, $this->createdBy, $this->requestId, $this->requestFingerprint, true, $this->version + 1);
    }
}
