<?php

declare(strict_types=1);

namespace App\Communities\Domain;

use DateTimeImmutable;

/** One period of membership, half-open: it covers its start and ends exactly at its end. Rejoining starts a new one. */
final readonly class Membership
{
    private function __construct(
        public string $id,
        public string $communityId,
        public string $personId,
        public DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $endedAt,
        public int $version,
    ) {
    }

    public static function start(string $id, string $communityId, string $personId, DateTimeImmutable $now): self
    {
        return new self($id, $communityId, $personId, $now, null, 0);
    }

    public static function reconstitute(string $id, string $communityId, string $personId, DateTimeImmutable $startedAt, ?DateTimeImmutable $endedAt, int $version): self
    {
        return new self($id, $communityId, $personId, $startedAt, $endedAt, $version);
    }

    public function covers(DateTimeImmutable $at): bool
    {
        return $at >= $this->startedAt && (null === $this->endedAt || $at < $this->endedAt);
    }

    public function end(DateTimeImmutable $at): self
    {
        if (!$this->covers($at)) {
            throw CommunityException::conflict('This membership has already ended.');
        }

        return new self($this->id, $this->communityId, $this->personId, $this->startedAt, $at, $this->version + 1);
    }
}
