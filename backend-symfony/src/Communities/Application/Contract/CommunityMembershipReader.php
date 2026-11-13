<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

use DateTimeImmutable;

/** Published: which active communities a person belongs to at an instant, for contexts that restrict content to groups. */
interface CommunityMembershipReader
{
    /** @return list<string> community ids, sorted */
    public function activeCommunityIds(string $personId, DateTimeImmutable $at): array;
}
