<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

/**
 * What the push provider said. `state` is ACCEPTED (ticket issued), PENDING (no receipt yet), PROVIDER_ACCEPTED
 * (receipt ok), RETRY (rate limited or temporarily unavailable), FAILED (rejected) or UNKNOWN (the send may have
 * reached the provider).
 */
final readonly class PushTransportResult
{
    public function __construct(public string $state, public ?string $ticketId, public string $errorCode)
    {
    }
}
