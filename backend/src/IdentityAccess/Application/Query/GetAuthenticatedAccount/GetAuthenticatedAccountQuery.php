<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\GetAuthenticatedAccount;

use App\Shared\Application\CQRS\Query;

final readonly class GetAuthenticatedAccountQuery implements Query
{
    public function __construct()
    {
    }
}
