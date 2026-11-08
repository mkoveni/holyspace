<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\UpdateServingTeam;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class UpdateServingTeamCommand implements Command
{
    public function __construct(public string $actorId, public string $teamId, public int $expectedVersion, public ?string $name, public bool $active)
    {
    }
}
