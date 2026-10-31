<?php

declare(strict_types=1);

namespace App\ServicePlanning\Infrastructure\Persistence;

use App\ServicePlanning\Domain\Model\ServicePlan;
use App\Shared\Infrastructure\Persistence\WorkflowDocumentMapper;

final class ServicePlanningMapper extends WorkflowDocumentMapper
{
    protected function types(): array
    {
        return ['plans' => ServicePlan::class];
    }
}
