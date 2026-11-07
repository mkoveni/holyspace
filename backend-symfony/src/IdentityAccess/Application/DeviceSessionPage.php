<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application;

final readonly class DeviceSessionPage
{
    /** @param list<DeviceSessionView> $items */
    public function __construct(public array $items, public bool $hasMore)
    {
    }
}
