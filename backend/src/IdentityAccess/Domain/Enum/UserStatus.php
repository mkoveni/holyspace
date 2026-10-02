<?php

declare(strict_types=1);

namespace App\IdentityAccess\Domain\Enum;

enum UserStatus: string
{
    case ACTIVE = 'active';
    case DISABLED = 'disabled';
    case LOCKED = 'locked';
}
