<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Domain;

enum CommunicationStatus: string
{
    case Draft = 'DRAFT';
    case Contacted = 'CONTACTED';
    case Cancelled = 'CANCELLED';
}
