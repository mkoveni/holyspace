<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\GetAccountSecurityState;

use App\Shared\Application\CQRS\Query;

final readonly class GetAccountSecurityStateQuery implements Query
{
    public function __construct(public string $userId)
    {
    }
}
