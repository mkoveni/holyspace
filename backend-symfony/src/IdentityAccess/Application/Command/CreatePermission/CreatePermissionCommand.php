<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\CreatePermission;

use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class CreatePermissionCommand implements Command
{
    public function __construct(public string $actorId, public string $code, public ?string $description)
    {
    }
}
