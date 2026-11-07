<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure;

use App\IdentityAccess\Application\Contract\AccountDirectory;
use App\IdentityAccess\Application\Contract\AccountReader;

final readonly class PublishedAccountDirectory implements AccountDirectory
{
    public function __construct(private AccountReader $accounts)
    {
    }

    public function active(string $accountId): bool
    {
        return $this->accounts->find($accountId)?->isActive() ?? false;
    }
}
