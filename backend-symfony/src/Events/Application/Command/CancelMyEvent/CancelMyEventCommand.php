<?php

declare(strict_types=1);

namespace App\Events\Application\Command\CancelMyEvent;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class CancelMyEventCommand implements Command
{
    public function __construct(public string $actorId, public string $occurrenceId, public int $expectedVersion)
    {
    }
}
