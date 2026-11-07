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
}
