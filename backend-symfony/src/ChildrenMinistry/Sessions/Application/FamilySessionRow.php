<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Sessions\Domain\MinistrySession;

final readonly class FamilySessionRow
{
    public function __construct(public MinistrySession $session, public string $programme, public string $className, public string $room, public string $location, public string $timeZone)
    {
    }
}
