<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Contract;

final readonly class RoleSummary
{
    /** @param list<string> $permissions codes granted by the role */
    public function __construct(public string $id, public string $name, public string $description, public array $permissions = [])
    {
    }
}
