<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Domain;

enum IncidentStatus: string
{
    case Open = 'OPEN';
    case InReview = 'IN_REVIEW';
    case Closed = 'CLOSED';
}
