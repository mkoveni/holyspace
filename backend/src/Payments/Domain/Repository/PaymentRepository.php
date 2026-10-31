<?php

declare(strict_types=1);

namespace App\Payments\Domain\Repository;

use App\Shared\Domain\Model\WorkflowRecord;

interface PaymentRepository
{
    public function save(WorkflowRecord $record): void;

    public function find(string $kind, string $id): ?WorkflowRecord;

    public function all(string $kind): array;

    public function byExternalKey(string $key): ?WorkflowRecord;

    public function notification(string $key): ?string;

    public function remember(string $key, string $fingerprint): void;
}
