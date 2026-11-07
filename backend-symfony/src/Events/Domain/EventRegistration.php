<?php

declare(strict_types=1);

namespace App\Events\Domain;

use App\Shared\Domain\Revision;
use DateTimeImmutable;

/** One person's place on one occurrence. A cancelled row is reused if the person registers again. */
final readonly class EventRegistration
{
    public string $id;
    public string $occurrenceId;
    public string $personId;

    private function __construct(
        string $id,
        string $occurrenceId,
        string $personId,
        public string $status,
        public int $position,
        public Revision $revision,
    ) {
        $this->id = EventRules::id($id);
        $this->occurrenceId = EventRules::id($occurrenceId);
        $this->personId = EventRules::id($personId);
        if (!\in_array($status, ['CONFIRMED', 'WAITLISTED', 'CANCELLED'], true) || $position < 1) {
            throw EventException::invalid('Invalid registration state.');
        }
    }

    public static function register(string $id, string $occurrenceId, string $personId, bool $waitlisted, int $position, DateTimeImmutable $now): self
    {
        return new self($id, $occurrenceId, $personId, $waitlisted ? 'WAITLISTED' : 'CONFIRMED', $position, Revision::start($now));
    }

    public static function reconstitute(string $id, string $occurrenceId, string $personId, string $status, int $position, Revision $revision): self
    {
        return new self($id, $occurrenceId, $personId, $status, $position, $revision);
    }

    public function cancel(DateTimeImmutable $now): self
    {
        return new self($this->id, $this->occurrenceId, $this->personId, 'CANCELLED', $this->position, $this->revision->advance($now));
    }

    public function rejoin(bool $waitlisted, int $position, DateTimeImmutable $now): self
    {
        if ('CANCELLED' !== $this->status) {
            throw EventException::conflict('The person already has an active registration.');
        }

        return new self($this->id, $this->occurrenceId, $this->personId, $waitlisted ? 'WAITLISTED' : 'CONFIRMED', $position, $this->revision->advance($now));
    }
}
