<?php

declare(strict_types=1);

namespace App\Events\Application\Command\RegisterMyEvent;

use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class RegisterMyEventCommand implements Command
{
    public function __construct(public string $actorId, public string $requestId, public string $occurrenceId, public ?int $expectedVersion)
    {
    }
}
