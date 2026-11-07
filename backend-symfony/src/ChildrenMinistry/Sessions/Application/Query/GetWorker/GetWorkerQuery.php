<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\GetWorker;

final readonly class GetWorkerQuery
{
    public function __construct(public string $personId)
    {
    }
}
