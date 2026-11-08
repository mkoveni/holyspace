<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

/** `verified` is false, with no items, when the account has no verified person. */
final readonly class MemberCommunityPage
{
    /** @param list<MemberCommunity> $items */
    public function __construct(public bool $verified, public array $items, public bool $hasMore)
    {
    }
}
