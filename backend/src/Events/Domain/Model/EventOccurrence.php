<?php

declare(strict_types=1);

namespace App\Events\Domain\Model;

use App\Events\Domain\ValueObject\OccurrenceId;
use App\Shared\Domain\ValueObject\AuditTimestamps;
use DateTimeImmutable;
use InvalidArgumentException;

final class EventOccurrence
{
    private function __construct(
        private readonly OccurrenceId $id,
        private DateTimeImmutable $startsAt,
        private DateTimeImmutable $endsAt,
        private string $location,
        private string $status,
        private readonly AuditTimestamps $audit,
    ) {
    }

    public static function schedule(DateTimeImmutable $start, DateTimeImmutable $end, string $location): self
    {
        self::validate($start, $end, $location);

        return new self(OccurrenceId::generate(), $start, $end, trim($location), 'scheduled', AuditTimestamps::now());
    }

    public static function reconstitute(OccurrenceId $id, DateTimeImmutable $start, DateTimeImmutable $end, string $location, string $status, AuditTimestamps $audit): self
    {
        self::validate($start, $end, $location);
        if (!in_array($status, ['scheduled', 'cancelled'], true)) {
            throw new InvalidArgumentException('Invalid occurrence status.');
        }

        return new self($id, $start, $end, $location, $status, $audit);
    }

    public function reschedule(DateTimeImmutable $start, DateTimeImmutable $end, string $location): void
    {
        self::validate($start, $end, $location);
        $this->startsAt = $start;
        $this->endsAt = $end;
        $this->location = trim($location);
        $this->audit->touch();
    }

    public function changeStatus(string $status): void
    {
        if (!in_array($status, ['scheduled', 'cancelled'], true)) {
            throw new InvalidArgumentException('Invalid occurrence status.');
        }
        $this->status = $status;
        $this->audit->touch();
    }

    private static function validate(DateTimeImmutable $start, DateTimeImmutable $end, string $location): void
    {
        if ($end <= $start) {
            throw new InvalidArgumentException('End time must be after start time.');
        }
        if ('' === trim($location) || mb_strlen($location) > 255) {
            throw new InvalidArgumentException('Location is required and must be at most 255 characters.');
        }
    }

    public function id(): OccurrenceId
    {
        return $this->id;
    }

    public function startsAt(): DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function endsAt(): DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function location(): string
    {
        return $this->location;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->audit->createdAt();
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->audit->updatedAt();
    }
}
