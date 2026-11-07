<?php

declare(strict_types=1);

namespace App\Shared\Domain;

enum ErrorKind
{
    case Invalid;
    case NotFound;
    case Conflict;
    case Unauthenticated;
    case Forbidden;
    case Throttled;
}
