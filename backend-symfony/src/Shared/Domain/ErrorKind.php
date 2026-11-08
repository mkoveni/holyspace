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
    /** A provider or deployment dependency is not available now; the client may retry (HTTP 503). */
    case Unavailable;
}
