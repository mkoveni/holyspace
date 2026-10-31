<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Infrastructure\ReadModel;

use App\ChildrenMinistry\Application\ReadModel\ChildrenMinistryReadStore;
use App\ChildrenMinistry\Domain\Repository\ChildrenMinistryRepository;
use App\Shared\Domain\Model\WorkflowRecord;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ChildrenMinistryReadStore::class)]
final readonly class RepositoryChildrenMinistryReadStore implements ChildrenMinistryReadStore
{
    public function __construct(private ChildrenMinistryRepository $records)
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
