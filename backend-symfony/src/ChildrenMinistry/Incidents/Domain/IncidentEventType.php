<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Domain;

enum IncidentEventType: string
{
    case Reported = 'REPORTED';
    case Triaged = 'TRIAGED';
    case Action = 'ACTION';
    case GuardianContact = 'GUARDIAN_CONTACT';
    case Closed = 'CLOSED';
    case Reopened = 'REOPENED';
}
