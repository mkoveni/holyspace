<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

/** Java `CommunityReader.Roster`: one roster row with the person's name ("Unavailable person" when withheld). */
final readonly class RosterEntryView
{
    public function __construct(
        public string $id,
        public string $meetingId,
        public string $personId,
        public ?string $membershipId,
        public bool $guest,
        public string $status,
        public int $version,
        public string $name,
    ) {
    }
}
