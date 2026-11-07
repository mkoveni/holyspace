<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Command\CancelSessionSeries;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class CancelSessionSeriesCommand implements Command
{
    public function __construct(public string $actorId, public string $seriesId, public int $expectedVersion, public string $reason)
    {
    }
}
