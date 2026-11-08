<?php

declare(strict_types=1);

namespace App\Volunteering\Infrastructure;

use App\Communications\Application\Contract\MemberNotificationKind;
use App\Communications\Application\Contract\MemberNotifications;
use App\Volunteering\Application\Contract\DutyNotices;

/** Generic duty notices in the volunteer's Communications inbox, inside the planning transaction (Java `RosterPlanning.notifyVolunteer`). */
final readonly class InboxDutyNotices implements DutyNotices
{
    public function __construct(private MemberNotifications $notifications)
    {
    }

    public function assigned(?string $accountId, string $sourceKey): void
    {
        $this->notifications->publish($accountId, MemberNotificationKind::DutyAssigned, $sourceKey);
    }

    public function changed(?string $accountId, string $sourceKey): void
    {
        $this->notifications->publish($accountId, MemberNotificationKind::DutyChanged, $sourceKey);
    }

    public function invited(?string $accountId, string $sourceKey): void
    {
        $this->notifications->publish($accountId, MemberNotificationKind::VolunteerInvitation, $sourceKey);
    }
}
