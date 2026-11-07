<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

use App\Events\Domain\EventNotification;

interface EventNotificationReader
{
    /** The approved notice with this request id, if any. */
    public function notification(string $id): ?EventNotification;
}
