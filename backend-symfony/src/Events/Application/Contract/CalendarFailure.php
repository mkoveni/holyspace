<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

use RuntimeException;

/** A scheduling rule refused a request; the status mirrors the HTTP status the Events API would have used. */
final class CalendarFailure extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}
