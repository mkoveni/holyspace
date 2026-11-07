<?php

declare(strict_types=1);

namespace App\Communities\Domain;

use DateTimeImmutable;

final readonly class GroupMeeting
{
    public function __construct(
        public string $id,
        public string $communityId,
        public string $occurrenceId,
        public DateTimeImmutable $scheduledAt,
        public string $status,
        public bool $rosterFrozen,
        public int $version,
    ) {
        if (!in_array($status, ['OPEN', 'CANCELLED', 'CLOSED'], true)) {
            throw CommunityException::invalid('Choose a valid meeting status.');
        }
    }

    public function change(string $status, bool $rosterFrozen): self
    {
        return new self(
            $this->id,
            $this->communityId,
            $this->occurrenceId,
            $this->scheduledAt,
            $status,
            $rosterFrozen,
            $this->version + 1
        );
    }
}
