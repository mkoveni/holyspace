<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\IsAccountVerified;

use App\Shared\Application\CQRS\Query;

final readonly class IsAccountVerifiedQuery implements Query
{
    public function __construct()
    {
    }
}
