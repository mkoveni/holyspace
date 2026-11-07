<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Contract;

interface AccountDirectory
{
    public function active(string $accountId): bool;
}
