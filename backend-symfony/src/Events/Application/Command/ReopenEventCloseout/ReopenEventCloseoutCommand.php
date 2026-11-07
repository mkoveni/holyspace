<?php

declare(strict_types=1);

namespace App\Events\Application\Command\ReopenEventCloseout;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class ReopenEventCloseoutCommand implements Command
{
    public function __construct(public string $actorId, public string $occurrenceId, public int $expectedVersion, public string $reason)
    {
    }
}
