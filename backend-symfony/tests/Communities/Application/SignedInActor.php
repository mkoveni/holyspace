<?php

declare(strict_types=1);

namespace App\Tests\Communities\Application;

use App\IdentityAccess\Application\Contract\Actor;
use App\IdentityAccess\Application\Contract\CurrentActor;

final readonly class SignedInActor implements CurrentActor
{
    public function __construct(private string $accountId)
    {
    }

    public function current(): Actor
    {
        return new Actor($this->accountId, 'session');
    }
}
