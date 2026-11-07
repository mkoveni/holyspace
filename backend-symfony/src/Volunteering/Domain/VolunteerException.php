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

    public static function conflict(string $message): self
    {
        return new self(ErrorKind::Conflict, $message);
    }
}
