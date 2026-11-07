<?php

declare(strict_types=1);

namespace App\IdentityAccess\Domain\ValueObject;

use App\IdentityAccess\Domain\IdentityRules;

final readonly class DisplayName
{
    public string $value;

    public function __construct(?string $value)
    {
        $this->value = IdentityRules::name($value);
    }
}
