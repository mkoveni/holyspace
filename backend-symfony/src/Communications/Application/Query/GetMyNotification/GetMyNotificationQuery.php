<?php

declare(strict_types=1);

namespace App\Communications\Application\Query\GetMyNotification;

final readonly class GetMyNotificationQuery
{
    public function __construct(public string $notificationId)
    {
    }
}
