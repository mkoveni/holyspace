<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\GetAuthenticatedAccount;

use App\IdentityAccess\Application\Port\AuthenticatedAccount;
use App\IdentityAccess\Domain\Model\UserAccount;
use App\Shared\Application\Exception\AccessDenied;

final readonly class GetAuthenticatedAccountHandler
{
    public function __construct(private AuthenticatedAccount $tokens)
    {
    }

    public function __invoke(GetAuthenticatedAccountQuery $message): UserAccount
    {
        $user = $this->tokens->user();
        if (null === $user || 'active' !== $user->status()->value) {
            throw new AccessDenied('An active account is required.');
        }

        return $user;
    }
}
