<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Contract;

final readonly class RoleSummary
{
    public function __construct(public string $id, public string $name, public string $description)
    {
    }
}
