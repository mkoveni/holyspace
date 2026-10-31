<?php

declare(strict_types=1);

namespace App\LifeEvents\Domain\Model;

use App\LifeEvents\Domain\Details\EngagementDetails;
use App\LifeEvents\Domain\Details\GenericLifeEventDetails;
use App\LifeEvents\Domain\Details\LifeEventDetails;
use App\LifeEvents\Domain\Details\MarriageDetails;
use App\LifeEvents\Domain\Enum\LifeEventStatus;
use App\LifeEvents\Domain\Enum\LifeEventType;
use App\LifeEvents\Domain\Enum\ParticipantRole;
use App\LifeEvents\Domain\Event\LifeEventCancelled;
use App\LifeEvents\Domain\Event\LifeEventRecorded;
use App\LifeEvents\Domain\Event\LifeEventUpdated;
use App\LifeEvents\Domain\Exception\InvalidLifeEvent;
use App\LifeEvents\Domain\Exception\InvalidLifeEventNotes;
use App\LifeEvents\Domain\ValueObject\LifeEventId;
use App\Shared\Domain\ValueObject\AuditTimestamps;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;

final class LifeEvent
{
    /** @var list<LifeEventParticipant> */
    private array $participants;
    /** @var list<object> */
    private array $domainEvents = [];

    private function __construct(
        private readonly LifeEventId $id, private LifeEventType $type, array $participants,
        private DateTimeImmutable $eventDate, private ?string $notes, private LifeEventStatus $status,
        private LifeEventDetails $details, private readonly AuditTimestamps $auditTimestamps,
    ) {
        $this->participants = $this->normalizeParticipants($participants);
    }

    /** @param list<LifeEventParticipant> $participants */
    public static function create(LifeEventId $id, LifeEventType $type, array $participants, DateTimeImmutable $eventDate, ?string $notes, LifeEventDetails $details, ?DateTimeImmutable $at = null): self
    {
        self::validate($type, $participants, $details);
        $event = new self($id, $type, $participants, self::normalizeDate($eventDate), self::normalizeNotes($notes), LifeEventStatus::ACTIVE, $details, AuditTimestamps::now($at));
        $event->domainEvents[] = new LifeEventRecorded($id->toString(), $type->value, $event->eventDate, $event->createdAt());

        return $event;
    }

    /** @param list<LifeEventParticipant> $participants */
    public static function reconstitute(LifeEventId $id, LifeEventType $type, array $participants, DateTimeImmutable $eventDate, ?string $notes, LifeEventStatus $status, LifeEventDetails $details, AuditTimestamps $auditTimestamps): self
    {
        self::validate($type, $participants, $details);

        return new self($id, $type, $participants, self::normalizeDate($eventDate), self::normalizeNotes($notes), $status, $details, $auditTimestamps);
    }

    public function id(): LifeEventId
    {
        return $this->id;
    }

    public function type(): LifeEventType
    {
        return $this->type;
    }

    /** @return list<LifeEventParticipant> */
    public function participants(): array
    {
        return $this->participants;
    }

    /** @return list<\App\LifeEvents\Domain\ValueObject\PersonId> */
    public function personIds(): array
    {
        return array_map(static fn (LifeEventParticipant $p) => $p->personId(), $this->participants);
    }

    public function eventDate(): DateTimeImmutable
    {
        return $this->eventDate;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function status(): LifeEventStatus
    {
        return $this->status;
    }

    public function details(): LifeEventDetails
    {
        return $this->details;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->auditTimestamps->createdAt();
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->auditTimestamps->updatedAt();
    }

    public function update(LifeEventType $type, array $participants, DateTimeImmutable $eventDate, ?string $notes, LifeEventDetails $details, ?DateTimeImmutable $at = null): void
    {
        self::validate($type, $participants, $details);
        $this->type = $type;
        $this->participants = $this->normalizeParticipants($participants);
        $this->eventDate = self::normalizeDate($eventDate);
        $this->notes = self::normalizeNotes($notes);
        $this->details = $details;
        $this->auditTimestamps->touch($at);
        $this->domainEvents[] = new LifeEventUpdated($this->id->toString(), $this->updatedAt());
    }

    public function cancel(?DateTimeImmutable $at = null): void
    {
        if (LifeEventStatus::COMPLETED === $this->status) {
            throw InvalidLifeEvent::cannotCancelCompleted();
        }
        if (LifeEventStatus::CANCELLED !== $this->status) {
            $this->status = LifeEventStatus::CANCELLED;
            $this->auditTimestamps->touch($at);
            $this->domainEvents[] = new LifeEventCancelled($this->id->toString(), $this->updatedAt());
        }
    }

    public function restore(?DateTimeImmutable $at = null): void
    {
        if (LifeEventStatus::CANCELLED === $this->status) {
            $this->status = LifeEventStatus::ACTIVE;
            $this->auditTimestamps->touch($at);
            $this->domainEvents[] = new LifeEventUpdated($this->id->toString(), $this->updatedAt());
        }
    }

    public function complete(?DateTimeImmutable $at = null): void
    {
        if (LifeEventStatus::COMPLETED !== $this->status) {
            $this->status = LifeEventStatus::COMPLETED;
            $this->auditTimestamps->touch($at);
            $this->domainEvents[] = new LifeEventUpdated($this->id->toString(), $this->updatedAt());
        }
    }

    public function anniversaryIn(int $year): DateTimeImmutable
    {
        if (LifeEventType::MARRIAGE !== $this->type) {
            throw new LogicException('Anniversaries are only available for marriage life events.');
        }

        return $this->eventDate->setDate($year, (int) $this->eventDate->format('m'), (int) $this->eventDate->format('d'));
    }

    public function yearsMarriedOn(DateTimeImmutable $date): int
    {
        if (LifeEventType::MARRIAGE !== $this->type) {
            throw new LogicException('Years married is only available for marriage life events.');
        }

        return $this->eventDate->diff($date)->y;
    }

    /** @return list<object> */
    public function releaseDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    private static function validate(LifeEventType $type, array $participants, LifeEventDetails $details): void
    {
        if (count($participants) < 1) {
            throw InvalidLifeEvent::requiresParticipants();
        }
        $seen = [];
        foreach ($participants as $p) {
            if (!$p instanceof LifeEventParticipant) {
                throw new InvalidArgumentException('Participants must be LifeEventParticipant instances.');
            }
            $key = $p->personId()->toString();
            if (isset($seen[$key])) {
                throw InvalidLifeEvent::duplicateParticipants();
            }
            $seen[$key] = true;
        }
        if (LifeEventType::MARRIAGE === $type && (2 !== count($participants) || 2 !== count(array_filter($participants, fn ($p) => ParticipantRole::SPOUSE === $p->role())) || !($details instanceof MarriageDetails))) {
            throw InvalidLifeEvent::invalidMarriageParticipants();
        }
        if (LifeEventType::ENGAGEMENT === $type && (2 !== count($participants) || 2 !== count(array_filter($participants, fn ($p) => in_array($p->role(), [ParticipantRole::PROPOSER, ParticipantRole::RECIPIENT], true))) || !($details instanceof EngagementDetails))) {
            throw InvalidLifeEvent::invalidEngagementParticipants();
        }
        if (LifeEventType::MARRIAGE !== $type && LifeEventType::ENGAGEMENT !== $type && !($details instanceof GenericLifeEventDetails)) {
            throw InvalidLifeEvent::invalidDetails($type->value);
        }
    }

    private function normalizeParticipants(array $participants): array
    {
        usort($participants, static fn (LifeEventParticipant $a, LifeEventParticipant $b) => strcmp($a->personId()->toString(), $b->personId()->toString()));

        return array_values($participants);
    }

    private static function normalizeDate(DateTimeImmutable $date): DateTimeImmutable
    {
        return $date->setTime(0, 0, 0, 0);
    }

    private static function normalizeNotes(?string $notes): ?string
    {
        if (null === $notes) {
            return null;
        }
        $notes = trim($notes);
        if ('' === $notes) {
            return null;
        }
        if (mb_strlen($notes) > 2000) {
            throw InvalidLifeEventNotes::tooLong();
        }

        return $notes;
    }
}
