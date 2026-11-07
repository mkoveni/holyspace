<?php

declare(strict_types=1);

namespace App\Giving\Domain;

use App\Shared\Domain\ContextException;
use App\Shared\Domain\ErrorKind;

/** Every Giving failure; messages match the Java application. */
final class GivingException extends ContextException
{
    public static function invalid(string $message): self
    {
        return new self(ErrorKind::Invalid, $message);
    }

    public static function conflict(string $message): self
    {
        return new self(ErrorKind::Conflict, $message);
    }

    /** Java's translation of any unique or integrity violation raised by Giving writes. */
    public static function evidenceConflict(): self
    {
        return new self(
            ErrorKind::Conflict,
            'This fund name, gift reference, reversal or request conflicts with existing Giving evidence. Refresh and check the ledger before retrying.',
        );
    }

    /** Giving never says which record is missing, so a lookup cannot probe for donors or gifts. */
    public static function missing(): self
    {
        return new self(ErrorKind::NotFound, 'The Giving record was not found.');
    }

    public static function forbidden(): self
    {
        return new self(ErrorKind::Forbidden, 'Verified donor access is required.');
    }
}
