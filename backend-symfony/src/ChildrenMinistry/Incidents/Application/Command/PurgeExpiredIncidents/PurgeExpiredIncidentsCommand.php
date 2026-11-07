<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Application\Command\PurgeExpiredIncidents;

use App\Shared\Application\Command;

/** @implements Command<int> how many incidents lost their private details */
final readonly class PurgeExpiredIncidentsCommand implements Command
{
    public function __construct(public string $actorId, public int $limit)
    {
    }
}
