<?php

declare(strict_types=1);

namespace App\Tests\IdentityAccess\Application;

use App\IdentityAccess\Application\Contract\PersonBindingPolicy;

final readonly class FixedPolicy implements PersonBindingPolicy
{
    public function __construct(private bool $permits)
    {
    }

    public function permits(string $accountId, string $personId): bool
    {
        return $this->permits;
    }
}
