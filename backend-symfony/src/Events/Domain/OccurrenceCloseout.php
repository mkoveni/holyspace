<?php

declare(strict_types=1);

namespace App\Events\Domain;

use DateTimeImmutable;

/** The review that ends an occurrence's lifecycle. Closing stores the reviewed hash; reopening keeps all history. */
final readonly class OccurrenceCloseout
{
    private function __construct(
        public string $occurrenceId,
        public string $status,
        public int $version,
        public string $note,
        public string $followUp,
        public string $reviewHash,
        public ?string $updatedBy,
        public ?DateTimeImmutable $updatedAt,
    ) {
    }

    public static function open(string $occurrenceId): self
    {
        return new self($occurrenceId, 'OPEN', 0, '', '', '', null, null);
    }

    public static function reconstitute(string $occurrenceId, string $status, int $version, string $note, string $followUp, string $reviewHash, ?string $updatedBy, ?DateTimeImmutable $updatedAt): self
    {
        return new self($occurrenceId, $status, $version, $note, $followUp, $reviewHash, $updatedBy, $updatedAt);
    }

    /** @param bool $unresolved whether waitlisted people or undelivered notices remain, which makes follow-up mandatory */
    public function close(
        EventOccurrence $occurrence,
        int $expectedVersion,
        string $hash,
        string $note,
        string $followUp,
        bool $attendanceReviewed,
        bool $followUpReviewed,
        bool $unresolved,
        string $actorId,
        DateTimeImmutable $now,
    ): self {
        EventRules::version($this->version, $expectedVersion);
        if ('CLOSED' === $this->status) {
            throw EventException::conflict('This occurrence is already closed out.');
        }
        if ('SCHEDULED' === $occurrence->status && $now < $occurrence->endsAt) {
            throw EventException::conflict('Close out after the gathering ends, or after it is cancelled.');
        }
        if (!$attendanceReviewed || !$followUpReviewed) {
            throw EventException::invalid('Review attendance, waitlists and delivery outcomes before closing out.');
        }
        $nextNote = EventRules::text($note, 2000);
        $nextFollowUp = $unresolved ? EventRules::text($followUp, 2000) : EventRules::optional($followUp, 2000);

        return new self($this->occurrenceId, 'CLOSED', $this->version + 1, $nextNote, $nextFollowUp, $hash, $actorId, EventRules::instant($now));
    }

    public function reopen(int $expectedVersion, string $reason, string $actorId, DateTimeImmutable $now): self
    {
        EventRules::version($this->version, $expectedVersion);
        if ('CLOSED' !== $this->status) {
            throw EventException::conflict('Only a closed occurrence can be reopened for review.');
        }

        return new self($this->occurrenceId, 'OPEN', $this->version + 1, EventRules::text($reason, 2000), '', $this->reviewHash, $actorId, EventRules::instant($now));
    }
}
