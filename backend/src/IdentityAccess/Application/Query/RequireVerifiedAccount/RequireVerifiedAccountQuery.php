<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\RequireVerifiedAccount;

use App\Shared\Application\CQRS\Query;

final readonly class RequireVerifiedAccountQuery implements Query
{
    public function __construct()
    {
    }
}
