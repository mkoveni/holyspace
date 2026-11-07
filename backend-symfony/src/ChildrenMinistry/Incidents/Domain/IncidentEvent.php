<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Domain;

use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use DateTimeImmutable;

/** One step in an incident's history. The note is private and is blanked when the incident's retention ends. */
final readonly class IncidentEvent
{
    public string $note;

    public function __construct(
        public string $id,
        public string $incidentId,
        public int $revision,
        public IncidentEventType $type,
        public string $actorId,
        public DateTimeImmutable $occurredAt,
        string $note,
        public ?string $guardianId,
    ) {
        $this->note = ChildrenRules::text($note, 3000);
    }

    public function __debugInfo(): array
    {
        return ['id' => $this->id, 'type' => $this->type->value];
    }
}
