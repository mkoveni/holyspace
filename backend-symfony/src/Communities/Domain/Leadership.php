<?php

declare(strict_types=1);

namespace App\Communities\Domain;

use DateTimeImmutable;

final readonly class Leadership
{
    public function __construct(
        public string $id,
        public string $membershipId,
        public string $communityId,
        public ?string $accountId,
        public string $role,
        public DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $endedAt,
        public int $version,
    ) {
        if (!in_array($role, ['LEADER', 'CO_LEADER'], true)) {
            throw CommunityException::invalid('Choose leader or co-leader.');
        }
    }

    public function end(DateTimeImmutable $at): self
    {
        if ($this->endedAt !== null) {
            throw CommunityException::conflict('This appointment has already ended.');
        }

        return new self(
            $this->id,
            $this->membershipId,
            $this->communityId,
            $this->accountId,
            $this->role,
            $this->startedAt,
            CommunityRules::time($at),
            $this->version + 1
        );
    }
}
