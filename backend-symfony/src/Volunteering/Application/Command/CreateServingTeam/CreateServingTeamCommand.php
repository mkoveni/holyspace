<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\CreateServingTeam;

use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class CreateServingTeamCommand implements Command
{
    public function __construct(public string $actorId, public ?string $name)
    {
    }
}
