<?php

declare(strict_types=1);

namespace App\IdentityAccess\Domain;

use App\Shared\Domain\ContextException;
use App\Shared\Domain\ErrorKind;

final class IdentityException extends ContextException
{
    public static function invalid(string $message): self
    {
        return new self(ErrorKind::Invalid, $message);
    }

    public static function notFound(string $message): self
    {
        return new self(ErrorKind::NotFound, $message);
    }

    public static function conflict(string $message): self
    {
        return new self(ErrorKind::Conflict, $message);
    }

    public static function unauthenticated(string $message): self
    {
        return new self(ErrorKind::Unauthenticated, $message);
    }

    public static function forbidden(string $message): self
    {
        return new self(ErrorKind::Forbidden, $message);
    }

    public static function throttled(string $message): self
    {
        return new self(ErrorKind::Throttled, $message);
    }
}
