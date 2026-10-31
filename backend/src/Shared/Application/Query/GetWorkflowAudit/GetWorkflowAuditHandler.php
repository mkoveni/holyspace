<?php

declare(strict_types=1);

namespace App\Shared\Application\Query\GetWorkflowAudit;

use App\Shared\Application\DTO\WorkflowAuditPage;
use App\Shared\Application\ReadModel\WorkflowAuditReader;

final readonly class GetWorkflowAuditHandler
{
    public function __construct(private WorkflowAuditReader $reader)
    {
    }

    public function __invoke(GetWorkflowAuditQuery $message): WorkflowAuditPage
    {
        return $this->reader->page($message->context, $message->id, $message->limit, $message->before);
    }
}
