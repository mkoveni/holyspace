<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

use DateTimeImmutable;

final readonly class ShiftView
{
    public function __construct(
        public string $id,
        public string $teamId,
        public string $roleId,
        public string $sourceKind,
        public ?string $sourceId,
        public ?string $sourceRevision,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public string $status,
        public int $positions,
        public string $requestId = '',
        public string $requestHash = '',
        public int $version = 0,
        public int $scheduleRevision = 0,
    ) {
    }
}
