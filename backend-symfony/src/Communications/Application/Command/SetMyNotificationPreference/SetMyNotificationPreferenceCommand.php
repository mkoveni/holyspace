<?php

declare(strict_types=1);

namespace App\Communications\Application\Command\SetMyNotificationPreference;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class SetMyNotificationPreferenceCommand implements Command
{
    public function __construct(public string $topic, public bool $enabled, public int $expectedVersion)
    {
    }
}
