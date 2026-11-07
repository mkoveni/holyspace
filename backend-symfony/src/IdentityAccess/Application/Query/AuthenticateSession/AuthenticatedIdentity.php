<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\AuthenticateSession;

use App\IdentityAccess\Application\AccountView;

final readonly class AuthenticatedIdentity
{
    public function __construct(public string $sessionId, public AccountView $account)
    {
    }
}
