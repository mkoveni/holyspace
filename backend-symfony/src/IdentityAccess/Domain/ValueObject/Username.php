<?php

declare(strict_types=1);

namespace App\IdentityAccess\Domain\ValueObject;

use App\IdentityAccess\Domain\IdentityRules;

final readonly class Username
{
    public string $value;

    public function __construct(?string $value)
    {
        $this->value = IdentityRules::username($value);
    }
}
