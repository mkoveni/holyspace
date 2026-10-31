<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Domain\Repository;

use App\Shared\Domain\Model\WorkflowRecord;

interface ChildrenMinistryRepository
{
    public function save(WorkflowRecord $record): void;

    public function find(string $kind, string $id): ?WorkflowRecord;

    public function all(string $kind): array;
}
