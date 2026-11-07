<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

final readonly class FamilyVisit
{
    public function __construct(public FamilyAttendance $visit, public bool $canRequestPickupLabel)
    {
    }
}
