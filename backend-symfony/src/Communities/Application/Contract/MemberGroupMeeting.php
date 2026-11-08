<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

use DateTimeImmutable;

/** A meeting with its current Events schedule; CANCELLED when the occurrence is cancelled. */
final readonly class MemberGroupMeeting
{
    public function __construct(
        public string $id,
        public string $communityId,
        public string $communityName,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public string $timeZone,
        public string $venue,
        public string $status,
        public string $attendance,
    ) {
    }
}
