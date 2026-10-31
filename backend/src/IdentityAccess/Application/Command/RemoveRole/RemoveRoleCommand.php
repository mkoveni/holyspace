<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\RemoveRole;

use App\Shared\Application\CQRS\Command;

final readonly class RemoveRoleCommand implements Command
{
    public function __construct(public string $userId, public string $roleId)
    {
    }
}
