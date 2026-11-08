<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

use DateTimeImmutable;

final readonly class LeadershipView
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
        public string $personId,
    ) {
    }
}
