<?php

declare(strict_types=1);

namespace App\Events\Application\DTO;

use App\Events\Domain\Model\ChurchEvent;
use App\Events\Domain\Model\EventOccurrence;
use DateTimeZone;

final readonly class EventView
{
    public function __construct(public string $id, public string $title, public string $type, public ?string $description, public string $timezone, public string $recurrence, public array $occurrences, public string $createdAt, public string $updatedAt)
    {
    }

    public static function fromDomain(ChurchEvent $event): self
    {
        $zone = new DateTimeZone($event->timezone());

        return new self($event->id()->toString(), $event->title(), $event->type(), $event->description(), $event->timezone(), $event->recurrence(), array_map(static fn (EventOccurrence $o) => ['id' => $o->id()->toString(), 'eventId' => $event->id()->toString(), 'startsAt' => $o->startsAt()->setTimezone($zone)->format(DATE_ATOM), 'endsAt' => $o->endsAt()->setTimezone($zone)->format(DATE_ATOM), 'location' => $o->location(), 'status' => $o->status(), 'createdAt' => $o->createdAt()->format(DATE_ATOM), 'updatedAt' => $o->updatedAt()->format(DATE_ATOM)], $event->occurrences()), $event->createdAt()->format(DATE_ATOM), $event->updatedAt()->format(DATE_ATOM));
    }
}
