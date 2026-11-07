<?php

declare(strict_types=1);

namespace App\Tests\Giving\Application;

use App\IdentityAccess\Application\Contract\AuthenticatedAccount;

/** In-memory test double: the trusted caller is fixed. */
final readonly class FixedAccount implements AuthenticatedAccount
{
    public function __construct(private string $accountId)
    {
    }

    public function requireAccount(): string
    {
        return $this->accountId;
    }
}
