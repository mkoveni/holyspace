<?php

declare(strict_types=1);

namespace App\Events\Domain;

use App\Shared\Domain\ContextException;
use App\Shared\Domain\ErrorKind;

final class EventException extends ContextException
{
    public static function invalid(string $message): self
    {
        return new self(ErrorKind::Invalid, $message);
    }

    public static function missing(string $name): self
    {
        return new self(ErrorKind::NotFound, $name.' was not found.');
    }

    public static function conflict(string $message): self
    {
        return new self(ErrorKind::Conflict, $message);
    }

    public static function throttled(string $message): self
    {
        return new self(ErrorKind::Throttled, $message);
    }
}
