<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\GetUserAccount;

use App\Shared\Application\CQRS\Query;

final readonly class GetUserAccountQuery implements Query
{
    public function __construct(public string $userId)
    {
    }
}
