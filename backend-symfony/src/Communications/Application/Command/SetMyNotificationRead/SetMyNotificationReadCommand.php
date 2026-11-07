<?php

declare(strict_types=1);

namespace App\Communications\Application\Command\SetMyNotificationRead;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class SetMyNotificationReadCommand implements Command
{
    public function __construct(public string $notificationId, public bool $read)
    {
    }
}
