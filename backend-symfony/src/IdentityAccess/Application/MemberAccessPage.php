<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application;

final readonly class MemberAccessPage
{
    /** @param list<MemberAccessEntry> $links */
    public function __construct(public array $links, public bool $hasMore)
    {
    }
}
