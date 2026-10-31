<?php

declare(strict_types=1);

namespace App\Shared\Application\ReadModel;

use App\Shared\Application\DTO\WorkflowAuditPage;

interface WorkflowAuditReader
{
    public function page(string $context, string $recordId, int $limit, ?int $before): WorkflowAuditPage;
}
