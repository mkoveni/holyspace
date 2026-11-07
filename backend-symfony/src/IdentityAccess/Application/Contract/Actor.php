<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Contract;

final readonly class Actor
{
    public function __construct(public string $accountId, public string $sessionId)
    {
    }
}
