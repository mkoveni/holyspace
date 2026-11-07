<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Domain;

use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use DateTimeImmutable;

/** One scheduled run of a class in a room. Closed and cancelled sessions are terminal. */
final readonly class MinistrySession
{
    private function __construct(
        public string $id,
        public string $classId,
        public string $roomId,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public int $seatLimit,
        public SessionStatus $status,
        public int $version,
    ) {
        ChildrenRules::window($startsAt, $endsAt);
        if ($seatLimit < 1) {
            throw ChildrenException::invalid('Invalid session details.');
        }
    }

    public static function plan(string $id, string $classId, string $roomId, DateTimeImmutable $startsAt, DateTimeImmutable $endsAt, int $seatLimit): self
    {
        return new self($id, $classId, $roomId, $startsAt, $endsAt, $seatLimit, SessionStatus::Planned, 0);
    }

    public static function reconstitute(string $id, string $classId, string $roomId, DateTimeImmutable $startsAt, DateTimeImmutable $endsAt, int $seatLimit, SessionStatus $status, int $version): self
    {
        return new self($id, $classId, $roomId, $startsAt, $endsAt, $seatLimit, $status, $version);
    }

    public function withStatus(SessionStatus $next, int $expectedVersion): self
    {
        ChildrenRules::version($this->version, $expectedVersion);
        if ($this->status->terminal() && $next !== $this->status) {
            throw ChildrenException::conflict('This session is terminal and cannot be reopened.');
        }
        if (SessionStatus::Planned === $next && SessionStatus::Planned !== $this->status) {
            throw ChildrenException::conflict('An operational session cannot return to planned.');
        }

        return new self($this->id, $this->classId, $this->roomId, $this->startsAt, $this->endsAt, $this->seatLimit, $next, $this->version + 1);
    }

    /** Arrivals need an open session, from 30 minutes before its start until its end. */
    public function requireArrivalWindow(DateTimeImmutable $now): void
    {
        if (SessionStatus::Open !== $this->status || $now < $this->startsAt->modify('-1800 seconds') || $now >= $this->endsAt) {
            throw ChildrenException::conflict('Arrivals require an open session, from 30 minutes before its start until its end.');
        }
    }
}
