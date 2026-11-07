<?php

declare(strict_types=1);

namespace App\Tests\IdentityAccess\Application;

use App\IdentityAccess\Application\Contract\AccountDirectory;
use App\IdentityAccess\Application\Contract\AccountIdentityGuard;

final class FakeAccounts implements AccountDirectory, AccountIdentityGuard
{
    /** @param list<string> $activeAccounts */
    public function __construct(private readonly LockLog $log, private readonly array $activeAccounts)
    {
    }

    public function active(string $accountId): bool
    {
        return in_array($accountId, $this->activeAccounts, true);
    }

    public function lock(string $accountId): void
    {
        $this->log->entries[] = 'account';
    }
}
