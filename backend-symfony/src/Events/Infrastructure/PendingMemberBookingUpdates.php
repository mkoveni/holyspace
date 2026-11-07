<?php

declare(strict_types=1);

namespace App\Events\Infrastructure;

use App\Events\Application\Contract\MemberBookingUpdates;

/**
 * PLACEHOLDER: the Communications member inbox (V29) is not ported yet, so booking changes produce no inbox entry.
 * Booking, waitlist and pass behaviour is unaffected. Replace this class with an adapter over the inbox contract
 * (listed in docs/porting/events.md, decision D2) rather than extending it.
 */
final readonly class PendingMemberBookingUpdates implements MemberBookingUpdates
{
    public function publish(string $accountId, string $key): void
    {
    }
}
