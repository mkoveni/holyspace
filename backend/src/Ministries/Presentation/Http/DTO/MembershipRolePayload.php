<?php

declare(strict_types=1);

namespace App\Ministries\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class MembershipRolePayload
{
    public function __construct(#[Assert\Choice(choices: ['member', 'leader'])] public string $role)
    {
    }
}
