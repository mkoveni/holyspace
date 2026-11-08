<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

/**
 * A meeting of the caller's community with the caller's own attendance: the roster status, else NOT_LISTED once the
 * roster is frozen, else NOT_PREPARED. The occurrence id resolves the date through Events and is never exposed.
 */
final readonly class MemberMeetingRow
{
    public function __construct(
        public string $id,
        public string $communityId,
        public string $communityName,
        public string $occurrenceId,
        public string $status,
        public string $attendance,
    ) {
    }
}
