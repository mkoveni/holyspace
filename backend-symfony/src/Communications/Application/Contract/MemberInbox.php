<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

final readonly class MemberInbox
{
    /** @param list<MemberNotification> $items newest first */
    public function __construct(public array $items, public int $unreadCount, public bool $hasMore)
    {
    }
}
