<?php

declare(strict_types=1);

namespace App\Communities\Domain;

use DateTimeImmutable;

/** A leader or co-leader appointment on a current membership, optionally bound to a login account that gains scoped access. */
final readonly class Leadership
{
    private function __construct(
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

    public static function appoint(string $id, string $membershipId, string $communityId, ?string $accountId, string $role, DateTimeImmutable $now): self
    {
        return new self($id, $membershipId, $communityId, $accountId, $role, $now, null, 0);
    }

    public static function reconstitute(string $id, string $membershipId, string $communityId, ?string $accountId, string $role, DateTimeImmutable $startedAt, ?DateTimeImmutable $endedAt, int $version): self
    {
        return new self($id, $membershipId, $communityId, $accountId, $role, $startedAt, $endedAt, $version);
    }

    public function end(DateTimeImmutable $now): self
    {
        if (null !== $this->endedAt) {
            throw CommunityException::conflict('This appointment has already ended.');
        }

        return new self($this->id, $this->membershipId, $this->communityId, $this->accountId, $this->role, $this->startedAt, $now, $this->version + 1);
    }
}
