<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

/** The notices other contexts may place in a member's inbox. */
enum MemberNotificationKind: string
{
    case VolunteerInvitation = 'VOLUNTEER_INVITATION';
    case DutyChanged = 'DUTY_CHANGED';
    case DutyAssigned = 'DUTY_ASSIGNED';
    case EventBookingUpdated = 'EVENT_BOOKING_UPDATED';
}
