<?php

declare(strict_types=1);

namespace App\Volunteering\Infrastructure\ReadModel;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Volunteering\Application\ReadModel\VolunteeringReadStore;
use App\Volunteering\Domain\Repository\VolunteeringRepository;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(VolunteeringReadStore::class)]
final readonly class RepositoryVolunteeringReadStore implements VolunteeringReadStore
{
    public function __construct(private VolunteeringRepository $records)
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
