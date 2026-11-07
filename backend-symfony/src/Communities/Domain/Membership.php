<?php

declare(strict_types=1);

namespace App\Communities\Domain;

use DateTimeImmutable;

final readonly class Membership
{
    public function __construct(
        public string $id,
        public string $communityId,
        public string $personId,
        public DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $endedAt,
        public int $version,
    ) {
    }

    public function covers(DateTimeImmutable $at): bool
    {
        return $at >= $this->startedAt && ($this->endedAt === null || $at < $this->endedAt);
    }

    public function end(DateTimeImmutable $at): self
    {
        if (!$this->covers($at)) {
            throw CommunityException::conflict('This membership has already ended.');
        }

        return new self(
            $this->id,
            $this->communityId,
            $this->personId,
            $this->startedAt,
            CommunityRules::time($at),
            $this->version + 1
        );
    }
}
