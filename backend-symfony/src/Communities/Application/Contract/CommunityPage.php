<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

final readonly class CommunityPage
{
    /** @param list<CommunityView> $communities */
    public function __construct(public array $communities, public int $total)
    {
    }
}
