<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Domain;

enum IncidentSeverity: string
{
    case Low = 'LOW';
    case Moderate = 'MODERATE';
    case High = 'HIGH';
    case Critical = 'CRITICAL';
}
