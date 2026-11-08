<?php

declare(strict_types=1);

namespace App\Communities\Application\Command\PrepareGroupRoster;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class PrepareGroupRosterCommand implements Command
{
    public function __construct(public string $actorId, public string $meetingId, public int $expectedVersion)
    {
    }
}
