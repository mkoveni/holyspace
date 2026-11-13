<?php

declare(strict_types=1);

namespace App\Forms\Domain;

use App\Shared\Domain\ContextException;
use App\Shared\Domain\ErrorKind;

/** Every Forms failure: INVALID 400, MISSING 404, CONFLICT 409, FORBIDDEN 403, THROTTLED 429. */
final class FormException extends ContextException
{
    public static function invalid(string $message): self
    {
        return new self(ErrorKind::Invalid, $message);
    }

    public static function conflict(string $message): self
    {
        return new self(ErrorKind::Conflict, $message);
    }

    public static function forbidden(string $message): self
    {
        return new self(ErrorKind::Forbidden, $message);
    }

    public static function throttled(string $message): self
    {
        return new self(ErrorKind::Throttled, $message);
    }

    /** A form that does not exist, is not open to this caller or is not published look the same. */
    public static function missing(): self
    {
        return new self(ErrorKind::NotFound, 'Form was not found.');
    }
}
