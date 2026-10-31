<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\RequireVerifiedAccount;

use App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateHandler;
use App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateQuery;
use App\IdentityAccess\Application\Query\GetAuthenticatedAccount\GetAuthenticatedAccountHandler;
use App\IdentityAccess\Application\Query\GetAuthenticatedAccount\GetAuthenticatedAccountQuery;
use App\IdentityAccess\Application\Query\IsAccountVerified\IsAccountVerifiedHandler;
use App\IdentityAccess\Application\Query\IsAccountVerified\IsAccountVerifiedQuery;
use App\Shared\Application\Exception\AccessDenied;

final readonly class RequireVerifiedAccountHandler
{
    public function __construct(
        private GetAuthenticatedAccountHandler $getAuthenticatedAccountHandler,
        private GetAccountSecurityStateHandler $getAccountSecurityStateHandler,
        private IsAccountVerifiedHandler $isAccountVerifiedHandler,
    ) {
    }

    public function __invoke(RequireVerifiedAccountQuery $message): void
    {
        if (null === ($this->getAccountSecurityStateHandler)(new GetAccountSecurityStateQuery(($this->getAuthenticatedAccountHandler)(new GetAuthenticatedAccountQuery())->id()->toString()))['secret'] || !($this->isAccountVerifiedHandler)(new IsAccountVerifiedQuery())) {
            throw new AccessDenied('Authenticator verification is required.');
        }
    }
}
