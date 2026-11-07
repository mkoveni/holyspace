<?php

declare(strict_types=1);

namespace App\IdentityAccess\Domain\ValueObject;

use App\IdentityAccess\Domain\IdentityException;

enum AccountStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';

    public static function fromValue(string $value): self
    {
        return self::tryFrom(strtolower($value)) ?? throw IdentityException::invalid('Invalid account status.');
    }
}
