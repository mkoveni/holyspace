<?php

declare(strict_types=1);

namespace App\Tests\Giving\Application;

use App\IdentityAccess\Application\Contract\AccountDirectory;
use App\IdentityAccess\Application\Contract\AccountIdentityGuard;
use App\IdentityAccess\Application\Contract\AccountNames;

/** In-memory test double for the IdentityAccess account contracts Giving uses; account locks go to the shared log. */
final class FakeAccounts implements AccountDirectory, AccountIdentityGuard, AccountNames
{
    /** @var array<string, string> active accounts by id => display name */
    public array $active = [];
    /** @var array<string, string> */
    public array $names = [];

    public function __construct(private readonly LockLog $log = new LockLog())
    {
    }

    public function active(string $accountId): bool
    {
        return isset($this->active[$accountId]);
    }

    public function lock(string $accountId): void
    {
        $this->log->locks[] = 'account:'.$accountId;
    }

    public function names(array $ids): array
    {
        return array_intersect_key($this->names, array_flip($ids));
    }
}
