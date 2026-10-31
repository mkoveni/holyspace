<?php

declare(strict_types=1);

namespace App\Attendance\Infrastructure\ReadModel;

use App\Attendance\Application\ReadModel\AttendanceReadStore;
use App\Attendance\Domain\Repository\AttendanceRepository;
use App\Shared\Domain\Model\WorkflowRecord;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(AttendanceReadStore::class)]
final readonly class RepositoryAttendanceReadStore implements AttendanceReadStore
{
    public function __construct(private AttendanceRepository $records)
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
