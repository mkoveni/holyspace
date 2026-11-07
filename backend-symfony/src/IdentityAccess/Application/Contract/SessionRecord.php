<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Contract;

use DateTimeImmutable;

final readonly class SessionRecord
{
    public function __construct(public string $id, public string $accountId, public DateTimeImmutable $expiresAt)
    {
    }
}
