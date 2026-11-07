<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Domain;

enum IncidentCategory: string
{
    case Injury = 'INJURY';
    case Illness = 'ILLNESS';
    case Behaviour = 'BEHAVIOUR';
    case Safeguarding = 'SAFEGUARDING';
    case Other = 'OTHER';
}
