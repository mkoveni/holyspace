<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

/** A leader's name and role only: no person, membership, appointment or account ids. */
final readonly class MemberCommunityLeader
{
    public function __construct(public string $name, public string $role)
    {
    }
}
