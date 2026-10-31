<?php

declare(strict_types=1);

namespace App\Shared\Application\Query\GetWorkflowAudit;

use App\Shared\Application\CQRS\Query;

final readonly class GetWorkflowAuditQuery implements Query
{
    public function __construct(
        public string $context,
        public string $id,
        public int $limit = 50,
        public ?int $before = null,
    ) {
    }
}
