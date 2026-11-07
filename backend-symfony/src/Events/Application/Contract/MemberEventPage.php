<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

final readonly class MemberEventPage
{
    /** @param list<MemberEventItem> $items */
    public function __construct(public bool $verified, public array $items, public bool $hasMore)
    {
    }
}
