<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\GetCurrentAccount;

use App\IdentityAccess\Application\DTO\AuthenticatedUserView;
use App\IdentityAccess\Application\Port\AuthenticatedAccount;
use App\IdentityAccess\Application\Query\GetAccountSecurityStatus\GetAccountSecurityStatusHandler;
use App\IdentityAccess\Application\Query\GetAccountSecurityStatus\GetAccountSecurityStatusQuery;
use App\IdentityAccess\Application\Query\IsAccountVerified\IsAccountVerifiedHandler;
use App\IdentityAccess\Application\Query\IsAccountVerified\IsAccountVerifiedQuery;
use App\IdentityAccess\Application\ReadModel\IdentityAccessReadRepository;
use App\Shared\Application\Exception\AccessDenied;

final readonly class GetCurrentAccountHandler
{
    public function __construct(
        private AuthenticatedAccount $actor,
        private IdentityAccessReadRepository $permissions,
        private GetAccountSecurityStatusHandler $security,
        private IsAccountVerifiedHandler $verified,
    ) {
    }

    public function __invoke(GetCurrentAccountQuery $query): ?AuthenticatedUserView
    {
        $account = $this->actor->user();
        if (!$account) {
            return null;
        }
        if ('active' !== $account->status()->value) {
            throw new AccessDenied('Account is not active.');
        }
        $roles = array_map(fn ($role) => 'ROLE_'.$role->toString(), $account->roleIds()) ?: ['ROLE_USER'];

        return new AuthenticatedUserView($account->id()->toString(), $account->username()->value(), $account->personId(), $account->status()->value, $roles, $account->createdAt()->format(DATE_ATOM), $account->updatedAt()->format(DATE_ATOM), $this->permissions->permissionCodesForUser($account->id()->toString()), ($this->security)(new GetAccountSecurityStatusQuery())['enabled'], ($this->verified)(new IsAccountVerifiedQuery()));
    }
}
