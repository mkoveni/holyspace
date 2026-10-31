<?php

declare(strict_types=1);

namespace App\Reporting\Infrastructure\ReadModel;

use App\Reporting\Application\ReadModel\ReportingReadStore;
use App\Reporting\Domain\Repository\ReportingRepository;
use App\Shared\Domain\Model\WorkflowRecord;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ReportingReadStore::class)]
final readonly class RepositoryReportingReadStore implements ReportingReadStore
{
    public function __construct(private ReportingRepository $records)
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
