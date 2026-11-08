<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

use DateTimeImmutable;

final readonly class ParticipationEntry
{
    public function __construct(
        public string $communityId,
        public string $kind,
        public string $name,
        public string $membershipId,
        public DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $endedAt,
    ) {
    }
}
