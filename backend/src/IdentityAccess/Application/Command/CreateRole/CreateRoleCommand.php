<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\CreateRole;

use App\Shared\Application\CQRS\Command;

final readonly class CreateRoleCommand implements Command
{
    public function __construct(public string $name, public string $description = '')
    {
    }
}
