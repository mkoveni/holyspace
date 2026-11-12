<?php

declare(strict_types=1);

namespace App\People\Application\Contract;

/** Published so Events can welcome a visitor into membership without touching People aggregates. Runs inside the caller's transaction. */
interface MembershipConverter
{
    /**
     * Makes a VISITOR or REGULAR_VISITOR an active member.
     *
     * @return bool false when the person already was a member (nothing changed)
     */
    public function admit(string $actorId, string $personId): bool;
}
