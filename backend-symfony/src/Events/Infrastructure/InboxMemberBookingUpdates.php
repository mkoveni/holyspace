<?php

declare(strict_types=1);

namespace App\Events\Infrastructure;

use App\Communications\Application\Contract\MemberNotificationKind;
use App\Communications\Application\Contract\MemberNotifications;
use App\Events\Application\Contract\MemberBookingUpdates;

/** Places a generic "booking updated" notice in the member's Communications inbox, inside the booking transaction. */
final readonly class InboxMemberBookingUpdates implements MemberBookingUpdates
{
    public function __construct(private MemberNotifications $notifications)
    {
    }

    public function publish(string $accountId, string $key): void
    {
        $this->notifications->publish($accountId, MemberNotificationKind::EventBookingUpdated, $key);
    }
}
