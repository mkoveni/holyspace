<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\ListPermissions;

use App\IdentityAccess\Application\ReadModel\AccessCatalogue;

final readonly class ListPermissionsHandler
{
    public function __construct(private AccessCatalogue $reader)
    {
    }

    public function __invoke(ListPermissionsQuery $message): array
    {
        return $this->reader->permissions();
    }
}
