<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

/** SENT (accepted), FAILED (definitely not sent) or UNKNOWN (the outcome is uncertain), with a short error code. */
final readonly class TransportResult
{
    public function __construct(public string $status, public string $errorCode, public bool $captured)
    {
    }
}
