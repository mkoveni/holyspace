<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\IsAccountVerified;

use App\IdentityAccess\Application\Port\AuthenticatedAccount;

final readonly class IsAccountVerifiedHandler
{
    public function __construct(private AuthenticatedAccount $tokens)
    {
    }

    public function __invoke(IsAccountVerifiedQuery $message): bool
    {
        return $this->tokens->verified();
    }
}
