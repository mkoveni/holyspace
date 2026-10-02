<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Service;

use App\IdentityAccess\Domain\ValueObject\UserId;

interface PermissionChecker
{
    public function isGranted(UserId $userId, string $permissionCode): bool;
}
