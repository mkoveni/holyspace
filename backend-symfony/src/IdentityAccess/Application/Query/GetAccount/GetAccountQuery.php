<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\GetAccount;

final readonly class GetAccountQuery
{
    public function __construct(public string $id)
    {
    }
}
