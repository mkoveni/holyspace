<?php

declare(strict_types=1);

namespace App\Events\Domain\Repository;

use App\Events\Domain\EventNotification;

interface EventNotificationRepository
{
    public function insert(EventNotification $notification): void;
}
