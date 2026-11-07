<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\ListAccounts;

final readonly class ListAccountsQuery
{
    public function __construct(public string $search, public int $limit)
    {
    }
}
