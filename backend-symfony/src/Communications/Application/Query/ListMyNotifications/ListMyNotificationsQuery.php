<?php

declare(strict_types=1);

namespace App\Communications\Application\Query\ListMyNotifications;

final readonly class ListMyNotificationsQuery
{
    public function __construct(public bool $unreadOnly, public int $limit, public int $offset)
    {
    }
}
