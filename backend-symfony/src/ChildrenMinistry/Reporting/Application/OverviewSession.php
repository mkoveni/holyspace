<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Reporting\Application;

use DateTimeImmutable;

final readonly class OverviewSession
{
    public function __construct(public string $id, public string $programme, public string $ministryClass, public string $room, public DateTimeImmutable $startsAt, public string $status)
    {
    }
}
