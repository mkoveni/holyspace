<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure;

use App\IdentityAccess\Application\Contract\AccessReader;
use App\IdentityAccess\Application\Contract\AccountReader;
use App\IdentityAccess\Application\Contract\ResponsibleAccount;
use App\IdentityAccess\Application\Contract\ResponsibleAccountDirectory;
use App\IdentityAccess\Domain\IdentityRules;

final readonly class PublishedResponsibleAccountDirectory implements ResponsibleAccountDirectory
{
    private const int SEARCH_WINDOW = 100;
    private const int RESULT_LIMIT = 25;

    public function __construct(private AccountReader $accounts, private AccessReader $access)
    {
    }

    public function eligible(string $accountId, string $permission): bool
    {
        $account = $this->accounts->find($accountId);

        return null !== $account && $account->isActive() && $this->holds($accountId, $permission);
    }

    public function search(string $permission, string $search): array
    {
        $found = [];
        foreach ($this->accounts->search($search, self::SEARCH_WINDOW) as $account) {
            if ($account->isActive() && $this->holds($account->id, $permission)) {
                $found[] = new ResponsibleAccount($account->id, $account->displayName);
            }
            if (count($found) === self::RESULT_LIMIT) {
                break;
            }
        }

        return $found;
    }

    private function holds(string $accountId, string $permission): bool
    {
        return in_array($permission, IdentityRules::effectivePermissions($this->access->permissionCodes($accountId)), true);
    }
}
