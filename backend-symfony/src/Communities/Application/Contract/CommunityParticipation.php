<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

/** Published: a person's membership periods, without rosters, attendance or care information. */
interface CommunityParticipation
{
    /** @return list<ParticipationEntry> newest first */
    public function forPerson(string $personId, int $limit, int $offset): array;
}
