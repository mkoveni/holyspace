<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\ListAccounts;

use App\IdentityAccess\Application\AccountView;
use App\IdentityAccess\Application\Authorization\Authorization;
use App\IdentityAccess\Application\Contract\AccessReader;
use App\IdentityAccess\Application\Contract\AccountReader;
use App\IdentityAccess\Application\Contract\AccountSummary;
use App\IdentityAccess\Domain\IdentityRules;

final readonly class ListAccountsHandler
{
    public function __construct(private AccountReader $accounts, private AccessReader $access, private Authorization $authorization)
    {
    }

    /** @return list<AccountView> */
    public function handle(ListAccountsQuery $query): array
    {
        $this->authorization->requirePermission('identity.manage');

        $found = $this->accounts->search(IdentityRules::normalize($query->search), max(1, min(100, $query->limit)));
        $roles = $this->access->roleNamesByAccount(array_map(static fn (AccountSummary $account): string => $account->id, $found));

        return array_map(
            static fn (AccountSummary $account): AccountView => AccountView::from($account, $roles[$account->id] ?? [], []),
            $found,
        );
    }
}
