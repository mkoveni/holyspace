<?php

declare(strict_types=1);

namespace App\ServicePlanning\Application\ReadModel;

use App\Shared\Domain\Model\WorkflowRecord;

interface ServicePlanningReadStore
{
    public function find(string $kind, string $id): ?WorkflowRecord;

    public function all(string $kind): array;
}
