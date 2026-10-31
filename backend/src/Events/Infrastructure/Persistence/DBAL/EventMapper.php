<?php

declare(strict_types=1);

namespace App\Events\Infrastructure\Persistence\DBAL;

use App\Events\Domain\Model\ChurchEvent;
use App\Events\Domain\Model\EventOccurrence;
use App\Events\Domain\ValueObject\EventId;
use App\Events\Domain\ValueObject\OccurrenceId;
use App\Shared\Domain\ValueObject\AuditTimestamps;
use DateTimeImmutable;
use DateTimeZone;

final class EventMapper
{
    public function toRow(ChurchEvent $event): array
    {
        return ['id' => $event->id()->toString(), 'title' => $event->title(), 'type' => $event->type(), 'description' => $event->description(), 'timezone' => $event->timezone(), 'recurrence' => $event->recurrence(), 'created_at' => $event->createdAt()->format(DATE_ATOM), 'updated_at' => $event->updatedAt()->format(DATE_ATOM)];
    }

    public function occurrenceRow(EventOccurrence $occurrence, EventId $eventId): array
    {
        $utc = new DateTimeZone('UTC');

        return ['id' => $occurrence->id()->toString(), 'event_id' => $eventId->toString(), 'starts_at' => $occurrence->startsAt()->setTimezone($utc)->format(DATE_ATOM), 'ends_at' => $occurrence->endsAt()->setTimezone($utc)->format(DATE_ATOM), 'location' => $occurrence->location(), 'status' => $occurrence->status(), 'created_at' => $occurrence->createdAt()->format(DATE_ATOM), 'updated_at' => $occurrence->updatedAt()->format(DATE_ATOM)];
    }

    public function toDomain(array $row, array $occurrences): ChurchEvent
    {
        return ChurchEvent::reconstitute(EventId::fromString($row['id']), $row['title'], $row['type'], $row['description'], $row['timezone'], $row['recurrence'], array_map(static fn (array $o) => EventOccurrence::reconstitute(OccurrenceId::fromString($o['id']), new DateTimeImmutable($o['starts_at']), new DateTimeImmutable($o['ends_at']), $o['location'], $o['status'], new AuditTimestamps(new DateTimeImmutable($o['created_at']), new DateTimeImmutable($o['updated_at']))), $occurrences), new AuditTimestamps(new DateTimeImmutable($row['created_at']), new DateTimeImmutable($row['updated_at'])));
    }
}
