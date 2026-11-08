<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

final readonly class MemberPage
{
    /** @param list<MemberView> $members */
    public function __construct(public array $members, public int $total)
    {
    }
}
