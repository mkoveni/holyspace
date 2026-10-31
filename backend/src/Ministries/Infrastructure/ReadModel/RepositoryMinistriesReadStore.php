<?php

declare(strict_types=1);

namespace App\Ministries\Infrastructure\ReadModel;

use App\Ministries\Application\ReadModel\MinistriesReadStore;
use App\Ministries\Domain\Repository\MinistriesRepository;
use App\Shared\Domain\Model\WorkflowRecord;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(MinistriesReadStore::class)]
final readonly class RepositoryMinistriesReadStore implements MinistriesReadStore
{
    public function __construct(private MinistriesRepository $records)
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
