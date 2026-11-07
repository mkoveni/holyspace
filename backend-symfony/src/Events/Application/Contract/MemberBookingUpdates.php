<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

/** Tells a member their booking changed, in their inbox. Implemented over the Communications inbox (`InboxMemberBookingUpdates`). */
interface MemberBookingUpdates
{
    /** @param string $key identifies this revision of the booking, so a repeat is not a second notice */
    public function publish(string $accountId, string $key): void;
}
