<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\ListRoles;

use App\IdentityAccess\Application\ReadModel\AccessCatalogue;

final readonly class ListRolesHandler
{
    public function __construct(private AccessCatalogue $reader)
    {
    }

    public function __invoke(ListRolesQuery $message): array
    {
        return $this->reader->roles();
    }
}
