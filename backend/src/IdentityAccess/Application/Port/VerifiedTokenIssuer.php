<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Port;

use App\IdentityAccess\Domain\Model\UserAccount;

interface VerifiedTokenIssuer
{
    public function issue(UserAccount $account): string;
}
