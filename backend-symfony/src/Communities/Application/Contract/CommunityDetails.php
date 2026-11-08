<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

/** A community and whether the caller may run it (manage, or lead it now). */
final readonly class CommunityDetails
{
    public function __construct(public CommunityView $community, public bool $canOperate)
    {
    }
}
