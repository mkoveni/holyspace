<?php

declare(strict_types=1);

namespace App\Tests\Communications\Support;

use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use RuntimeException;

/** A caller whose session is valid for a fixed number of checks, then revoked. */
final class ExpiringAccount implements AuthenticatedAccount
{
    public function __construct(private readonly string $accountId, private int $liveChecks)
    {
    }

    public function requireAccount(): string
    {
        if ($this->liveChecks <= 0) {
            throw new RuntimeException('unauthenticated');
        }
        --$this->liveChecks;

        return $this->accountId;
    }
}
