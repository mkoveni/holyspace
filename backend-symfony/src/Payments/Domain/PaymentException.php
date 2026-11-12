<?php

declare(strict_types=1);

namespace App\Payments\Domain;

use App\Shared\Domain\ContextException;
use App\Shared\Domain\ErrorKind;

/** Every Payments failure (Java `PaymentException`): INVALID 400, MISSING 404, CONFLICT 409, UNAVAILABLE 503, THROTTLED 429 (public giving only). */
final class PaymentException extends ContextException
{
    public static function invalid(string $message): self
    {
        return new self(ErrorKind::Invalid, $message);
    }

    public static function conflict(string $message): self
    {
        return new self(ErrorKind::Conflict, $message);
    }

    public static function unavailable(string $message): self
    {
        return new self(ErrorKind::Unavailable, $message);
    }

    public static function throttled(string $message): self
    {
        return new self(ErrorKind::Throttled, $message);
    }

    /** Payments never says which record is missing, so a lookup cannot probe for orders or donors. */
    public static function missing(): self
    {
        return new self(ErrorKind::NotFound, 'Payment was not found.');
    }

    /** Java's translation of any integrity violation raised by a Payments write. */
    public static function evidenceConflict(): self
    {
        return new self(
            ErrorKind::Conflict,
            'This payment reference or monthly arrangement conflicts with existing evidence. Refresh before retrying.',
        );
    }

    public function isMissing(): bool
    {
        return ErrorKind::NotFound === $this->kind();
    }

    public function isConflict(): bool
    {
        return ErrorKind::Conflict === $this->kind();
    }
}
