<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

use DateTimeImmutable;

final readonly class MembershipView
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

    /** Half-open, as `Membership::covers`. */
    public function covers(DateTimeImmutable $at): bool
    {
        return $at >= $this->startedAt && (null === $this->endedAt || $at < $this->endedAt);
    }
}
