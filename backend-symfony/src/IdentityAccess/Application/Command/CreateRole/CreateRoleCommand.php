<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\CreateRole;

use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class CreateRoleCommand implements Command
{
    public function __construct(public string $actorId, public string $name, public ?string $description)
    {
    }
}
