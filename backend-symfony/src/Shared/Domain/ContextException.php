<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use RuntimeException;

/**
 * Base for every bounded context's domain failure (IdentityException, PeopleException, ...).
 * The kind decides the HTTP status; the message is safe to show to API clients.
 */
abstract class ContextException extends RuntimeException
{
    public function __construct(private readonly ErrorKind $kind, string $message)
    {
        parent::__construct($message);
    }

    public function kind(): ErrorKind
    {
        return $this->kind;
    }

    /** Seconds a throttled client should wait; contexts with a longer window override it. */
    public function retryAfterSeconds(): int
    {
        return 60;
    }
}
