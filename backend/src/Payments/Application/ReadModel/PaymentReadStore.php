<?php

declare(strict_types=1);

namespace App\Payments\Application\ReadModel;

use App\Shared\Domain\Model\WorkflowRecord;

interface PaymentReadStore
{
    public function find(string $kind, string $id): ?WorkflowRecord;

    public function all(string $kind): array;

    public function byExternalKey(string $key): ?WorkflowRecord;

    public function notification(string $key): ?string;
}
