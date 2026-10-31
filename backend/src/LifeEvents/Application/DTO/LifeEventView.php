<?php

declare(strict_types=1);

namespace App\LifeEvents\Application\DTO;

use App\LifeEvents\Domain\Model\LifeEvent;
use App\LifeEvents\Domain\Model\LifeEventParticipant;

final readonly class LifeEventView
{
    /**
     * @param list<LifeEventParticipantView> $participants
     * @param array<string, mixed>           $details      event-specific attributes supplied by the caller
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $eventDate,
        public array $participants,
        public array $details,
        public ?string $notes,
        public string $status,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromDomain(LifeEvent $event): self
    {
        return new self(
            $event->id()->toString(),
            $event->type()->value,
            $event->eventDate()->format('Y-m-d'),
            array_map(static fn (LifeEventParticipant $participant): LifeEventParticipantView => new LifeEventParticipantView(
                $participant->personId()->toString(), $participant->role()->value,
            ), $event->participants()),
            $event->details()->toArray(),
            $event->notes(),
            $event->status()->value,
            $event->createdAt()->format(DATE_ATOM),
            $event->updatedAt()->format(DATE_ATOM),
        );
    }
}
