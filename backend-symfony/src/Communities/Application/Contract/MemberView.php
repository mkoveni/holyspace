<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

final readonly class MemberView
{
    public function __construct(public MembershipView $membership, public string $name)
    {
    }
}
