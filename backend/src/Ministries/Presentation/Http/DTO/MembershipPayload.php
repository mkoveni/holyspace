<?php

declare(strict_types=1);

namespace App\Ministries\Presentation\Http\DTO;

final readonly class MembershipPayload
{
    public function __construct(public string $ministryId, public string $personId, public string $joinedOn, public string $role = 'member')
    {
    }
}
