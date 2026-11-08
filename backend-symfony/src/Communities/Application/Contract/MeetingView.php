<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

use DateTimeImmutable;

/** Java `GroupMeeting` as the API shows it. */
final readonly class MeetingView
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
    }
}
