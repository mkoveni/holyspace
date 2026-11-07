<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure;

use App\IdentityAccess\Application\Contract\AccountIdentityGuard;
use App\IdentityAccess\Domain\IdentityException;
use App\IdentityAccess\Domain\Repository\AccountRepository;

final readonly class PublishedAccountIdentityGuard implements AccountIdentityGuard
{
    public function __construct(private AccountRepository $accounts)
    {
    }

    public function lock(string $accountId): void
    {
        $this->accounts->findForUpdate($accountId) ?? throw IdentityException::notFound('Account was not found.');
    }
}
