<?php

declare(strict_types=1);

namespace App\Events\Domain\Model;

use App\Events\Domain\ValueObject\EventId;
use App\Events\Domain\ValueObject\OccurrenceId;
use App\Shared\Domain\ValueObject\AuditTimestamps;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class ChurchEvent
{
    public const TYPES = ['service', 'meeting', 'conference', 'outreach', 'class', 'other'];

    private function __construct(
        private readonly EventId $id,
        private string $title,
        private string $type,
        private ?string $description,
        private readonly string $timezone,
        private readonly string $recurrence,
        private array $occurrences,
        private readonly AuditTimestamps $audit,
    ) {
    }

    public static function create(string $title, string $type, ?string $description, string $timezone, DateTimeImmutable $start, DateTimeImmutable $end, string $location, string $recurrence, int $count): self
    {
        self::validate($title, $type, $description);
        if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException('Invalid timezone.');
        }
        if (!in_array($recurrence, ['once', 'weekly'], true) || $count < 1 || $count > 52 || ('once' === $recurrence && 1 !== $count)) {
            throw new InvalidArgumentException('Choose one occurrence or a weekly schedule of 1 to 52 occurrences.');
        }
        $start = $start->setTimezone(new DateTimeZone($timezone));
        $end = $end->setTimezone(new DateTimeZone($timezone));
        $occurrences = [];
        for ($i = 0; $i < $count; ++$i) {
            $nextStart = $start->modify("+$i weeks");
            $nextEnd = $end->modify("+$i weeks");
            // Do not silently shift a meeting into a different local hour across DST.
            if ($nextStart->format('H:i') !== $start->format('H:i') || $nextEnd->format('H:i') !== $end->format('H:i')) {
                throw new InvalidArgumentException('A recurrence falls in a daylight-saving gap. Choose a different time.');
            }
            $occurrences[] = EventOccurrence::schedule($nextStart, $nextEnd, $location);
        }

        return new self(EventId::generate(), trim($title), $type, $description, $timezone, $recurrence, $occurrences, AuditTimestamps::now());
    }

    public static function reconstitute(EventId $id, string $title, string $type, ?string $description, string $timezone, string $recurrence, array $occurrences, AuditTimestamps $audit): self
    {
        return new self($id, $title, $type, $description, $timezone, $recurrence, $occurrences, $audit);
    }

    public function update(string $title, string $type, ?string $description): void
    {
        self::validate($title, $type, $description);
        $this->title = trim($title);
        $this->type = $type;
        $this->description = $description;
        $this->audit->touch();
    }

    private static function validate(string $title, string $type, ?string $description): void
    {
        if ('' === trim($title) || mb_strlen($title) > 200) {
            throw new InvalidArgumentException('Title is required and must be at most 200 characters.');
        }
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Invalid event type.');
        }
        if (null !== $description && mb_strlen($description) > 5000) {
            throw new InvalidArgumentException('Description must be at most 5000 characters.');
        }
    }

    public function occurrence(OccurrenceId $id): ?EventOccurrence
    {
        foreach ($this->occurrences as $occurrence) {
            if ($occurrence->id()->toString() === $id->toString()) {
                return $occurrence;
            }
        }

        return null;
    }

    public function id(): EventId
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    public function recurrence(): string
    {
        return $this->recurrence;
    }

    /** @return list<EventOccurrence> */
    public function occurrences(): array
    {
        return $this->occurrences;
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
