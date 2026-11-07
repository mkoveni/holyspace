<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Contract;

final readonly class PermissionSummary
{
    public function __construct(public string $id, public string $code, public string $description)
    {
    }
}
