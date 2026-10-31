<?php

declare(strict_types=1);

namespace App\Communications\Infrastructure\ReadModel;

use App\Communications\Application\ReadModel\CommunicationsReadStore;
use App\Communications\Domain\Repository\CommunicationsRepository;
use App\Shared\Domain\Model\WorkflowRecord;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(CommunicationsReadStore::class)]
final readonly class RepositoryCommunicationsReadStore implements CommunicationsReadStore
{
    public function __construct(private CommunicationsRepository $records)
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
