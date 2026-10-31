<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\GetAccountSecurityStatus;

use App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateHandler;
use App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateQuery;
use App\IdentityAccess\Application\Query\GetAuthenticatedAccount\GetAuthenticatedAccountHandler;
use App\IdentityAccess\Application\Query\GetAuthenticatedAccount\GetAuthenticatedAccountQuery;
use App\IdentityAccess\Application\Query\IsAccountVerified\IsAccountVerifiedHandler;
use App\IdentityAccess\Application\Query\IsAccountVerified\IsAccountVerifiedQuery;

final readonly class GetAccountSecurityStatusHandler
{
    public function __construct(
        private GetAuthenticatedAccountHandler $getAuthenticatedAccountHandler,
        private GetAccountSecurityStateHandler $getAccountSecurityStateHandler,
        private IsAccountVerifiedHandler $isAccountVerifiedHandler,
    ) {
    }

    public function __invoke(GetAccountSecurityStatusQuery $message): array
    {
        $state = ($this->getAccountSecurityStateHandler)(new GetAccountSecurityStateQuery(($this->getAuthenticatedAccountHandler)(new GetAuthenticatedAccountQuery())->id()->toString()));

        return ['enabled' => null !== $state['secret'], 'verified' => ($this->isAccountVerifiedHandler)(new IsAccountVerifiedQuery()), 'remainingRecoveryCodes' => count(json_decode($state['recovery_hashes'], true, 512, JSON_THROW_ON_ERROR))];
    }
}
