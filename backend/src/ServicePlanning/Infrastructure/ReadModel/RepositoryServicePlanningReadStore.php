<?php

declare(strict_types=1);

namespace App\ServicePlanning\Infrastructure\ReadModel;

use App\ServicePlanning\Application\ReadModel\ServicePlanningReadStore;
use App\ServicePlanning\Domain\Repository\ServicePlanningRepository;
use App\Shared\Domain\Model\WorkflowRecord;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ServicePlanningReadStore::class)]
final readonly class RepositoryServicePlanningReadStore implements ServicePlanningReadStore
{
    public function __construct(private ServicePlanningRepository $records)
    {
    }

    public function find(string $kind, string $id): ?WorkflowRecord
    {
        return $this->records->find($kind, $id);
    }

    public function all(string $kind): array
    {
        return $this->records->all($kind);
    }
}
