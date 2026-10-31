<?php

declare(strict_types=1);

namespace App\Giving\Infrastructure\ReadModel;

use App\Giving\Application\ReadModel\GivingReadStore;
use App\Giving\Domain\Repository\GivingRepository;
use App\Shared\Domain\Model\WorkflowRecord;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(GivingReadStore::class)]
final readonly class RepositoryGivingReadStore implements GivingReadStore
{
    public function __construct(private GivingRepository $records)
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
