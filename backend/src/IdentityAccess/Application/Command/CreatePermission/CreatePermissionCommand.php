<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\CreatePermission;

final readonly class CreatePermissionCommand
{
    public function __construct(public string $code, public string $description = '') {}
}
