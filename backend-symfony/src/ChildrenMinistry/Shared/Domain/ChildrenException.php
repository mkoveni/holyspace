<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Shared\Domain;

use App\Shared\Domain\ContextException;
use App\Shared\Domain\ErrorKind;
use Override;

/** The failure type for every Children Ministry module. */
final class ChildrenException extends ContextException
{
    /** Guardian-facing throttles (pickup code attempts) lock for fifteen minutes. */
    private const int THROTTLE_SECONDS = 900;

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

    public static function forbidden(): self
    {
        return new self(ErrorKind::Forbidden, 'This operation is outside your assigned session.');
    }

    public static function throttled(string $message): self
    {
        return new self(ErrorKind::Throttled, $message);
    }

    #[Override]
    public function retryAfterSeconds(): int
    {
        return self::THROTTLE_SECONDS;
    }
}
