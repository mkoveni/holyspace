<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Domain;

enum StaffRole: string
{
    case Leader = 'LEADER';
    case Assistant = 'ASSISTANT';
}
