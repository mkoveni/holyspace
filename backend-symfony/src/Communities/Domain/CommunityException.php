<?php

declare(strict_types=1);

namespace App\Communities\Domain;

use App\Shared\Domain\ContextException;
use App\Shared\Domain\ErrorKind;

final class CommunityException extends ContextException
{
    public static function invalid(string $message): self
    {
        return new self(ErrorKind::Invalid, $message);
    }

    public static function conflict(string $message): self
    {
        return new self(ErrorKind::Conflict, $message);
    }

    public static function missing(): self
    {
        return new self(ErrorKind::NotFound, 'The requested community record was not found.');
    }

    public static function forbidden(string $message): self
    {
        return new self(ErrorKind::Forbidden, $message);
    }

    /** A refusal from another context's published contract, keeping its message and the HTTP status it chose. */
    public static function refused(int $status, string $message): self
    {
        return new self(match ($status) {
            400 => ErrorKind::Invalid,
            403 => ErrorKind::Forbidden,
            404 => ErrorKind::NotFound,
            default => ErrorKind::Conflict,
        }, $message);
    }

    /** A membership, appointment, meeting or request the database rejected as overlapping or duplicated. */
    public static function clash(): self
    {
        return new self(ErrorKind::Conflict, 'This membership, leadership appointment, meeting or request conflicts with an existing record. Refresh before trying again.');
    }
}
