<?php

declare(strict_types=1);

namespace App\Shared\Application\Audit;

interface AuditHistory
{
    public function page(string $context, string $recordId, int $limit = 25, ?int $before = null): array;
}
