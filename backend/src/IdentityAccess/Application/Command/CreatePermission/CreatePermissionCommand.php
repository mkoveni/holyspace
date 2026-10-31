<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\CreatePermission;

use App\Shared\Application\CQRS\Command;

final readonly class CreatePermissionCommand implements Command
{
    public function __construct(public string $code, public string $description = '')
    {
    }
}
