<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Contract;

final readonly class MemberAdmission
{
    public function __construct(public string $personId, public int $version)
    {
    }
}
