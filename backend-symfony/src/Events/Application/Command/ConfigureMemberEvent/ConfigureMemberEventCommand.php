<?php

declare(strict_types=1);

namespace App\Events\Application\Command\ConfigureMemberEvent;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class ConfigureMemberEventCommand implements Command
{
    public function __construct(public string $actorId, public string $eventId, public bool $enabled, public int $expectedVersion)
    {
    }
}
