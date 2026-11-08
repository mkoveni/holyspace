<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\GetCommunityCover;

final readonly class GetCommunityCoverQuery
{
    public function __construct(public string $communityId)
    {
    }
}
