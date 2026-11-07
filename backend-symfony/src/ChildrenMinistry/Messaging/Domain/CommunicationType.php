<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Domain;

enum CommunicationType: string
{
    case ConsentRenewal = 'CONSENT_RENEWAL';
    case SessionReminder = 'SESSION_REMINDER';
}
