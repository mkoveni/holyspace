<?php

declare(strict_types=1);

namespace App\Volunteering\Domain;

use App\Shared\Domain\ContextException;
use App\Shared\Domain\ErrorKind;

final class VolunteerException extends ContextException
{
    public static function invalid(string $message): self
    {
        return new self(ErrorKind::Invalid, $message);
    }

    public static function missing(string $name): self
    {
        return new self(ErrorKind::NotFound, $name.' was not found.');
    }

    /** Java's `DuplicateKeyException` handler for Volunteering. */
    public static function clash(): self
    {
        return new self(ErrorKind::Conflict, 'This volunteer, account, name or request is already in use. Refresh before trying again.');
    }

    public static function conflict(string $message): self
    {
        return new self(ErrorKind::Conflict, $message);
    }

    public static function forbidden(): self
    {
        return new self(ErrorKind::Forbidden, 'You cannot perform this volunteer action.');
    }
}
