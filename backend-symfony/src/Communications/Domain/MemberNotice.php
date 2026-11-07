<?php

declare(strict_types=1);

namespace App\Communications\Domain;

/**
 * The fixed wording of each member inbox notice. Notices name a topic and a fixed member destination, never a URL,
 * and carry no source details: the member opens the destination to see what changed.
 */
enum MemberNotice: string
{
    case VolunteerInvitation = 'VOLUNTEER_INVITATION';
    case DutyChanged = 'DUTY_CHANGED';
    case DutyAssigned = 'DUTY_ASSIGNED';
    case EventBookingUpdated = 'EVENT_BOOKING_UPDATED';

    public const array TOPICS = ['VOLUNTEERING', 'EVENTS'];

    public function topic(): string
    {
        return self::EventBookingUpdated === $this ? 'EVENTS' : 'VOLUNTEERING';
    }

    public function title(): string
    {
        return match ($this) {
            self::VolunteerInvitation => 'You’re invited to serve',
            self::DutyChanged => 'Your serving plans have changed',
            self::DutyAssigned => 'A duty has been added',
            self::EventBookingUpdated => 'Your event booking was updated',
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::VolunteerInvitation => 'An invitation is ready for your response. Open My volunteering to review the current details.',
            self::DutyChanged => 'Please review your latest duty details and commitment in My volunteering.',
            self::DutyAssigned => 'Your serving plans have been updated. Open My volunteering for details and readiness.',
            self::EventBookingUpdated => 'Open My events to see your current booking status. This notice is not an admission pass.',
        };
    }

    public function destination(): string
    {
        return $this->topic();
    }
}
