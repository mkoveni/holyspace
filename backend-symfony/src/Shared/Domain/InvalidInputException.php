<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/** A shared value object (such as a calendar date) rejected its input; a 400 for any context. */
final class InvalidInputException extends ContextException
{
    public static function because(string $message): self
    {
        return new self(ErrorKind::Invalid, $message);
    }
}
