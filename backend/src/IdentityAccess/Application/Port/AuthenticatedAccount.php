<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Port;

use App\IdentityAccess\Domain\Model\UserAccount;

interface AuthenticatedAccount
{
    public function user(): ?UserAccount;

    public function verified(): bool;
}
