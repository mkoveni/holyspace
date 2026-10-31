<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\GetUserPermissions;

use App\Shared\Application\CQRS\Query;

final readonly class GetUserPermissionsQuery implements Query
{
    public function __construct(public string $userId)
    {
    }
}
