<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Command\CreateSessionSeries;

use App\ChildrenMinistry\Sessions\Application\SessionSeriesDraft;
use App\Shared\Application\Command;

/** @implements Command<string> the series id, the same one on every retry of the request */
final readonly class CreateSessionSeriesCommand implements Command
{
    public function __construct(public string $actorId, public string $requestId, public SessionSeriesDraft $draft)
    {
    }
}
