<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\GetAccount;

use App\IdentityAccess\Application\AccountView;
use App\IdentityAccess\Application\Authorization\Authorization;
use App\IdentityAccess\Application\Contract\AccessReader;
use App\IdentityAccess\Application\Contract\AccountReader;
use App\IdentityAccess\Domain\IdentityException;
use App\IdentityAccess\Domain\IdentityRules;

final readonly class GetAccountHandler
{
    public function __construct(
        private AccountReader $accounts,
        private AccessReader $access,
        private Authorization $authorization,
    ) {
    }

    public function handle(GetAccountQuery $query): AccountView
    {
        $this->authorization->requireSelfOrPermission($query->id, 'identity.manage');

        $account = $this->accounts->find($query->id) ?? throw IdentityException::notFound('Account not found');

        return AccountView::from(
            $account,
            $this->access->roleNames($account->id),
            IdentityRules::effectivePermissions($this->access->permissionCodes($account->id)),
        );
    }
}
