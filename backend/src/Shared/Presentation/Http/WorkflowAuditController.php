<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http;

use App\Shared\Application\Query\GetFieldHistory\GetFieldHistoryHandler;
use App\Shared\Application\Query\GetFieldHistory\GetFieldHistoryQuery;
use App\Shared\Application\Query\GetWorkflowAudit\GetWorkflowAuditHandler;
use App\Shared\Application\Query\GetWorkflowAudit\GetWorkflowAuditQuery;
use App\Shared\Presentation\Http\DTO\AuditFilter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

final class WorkflowAuditController extends WorkflowController
{
    #[Route('/api/workflows/{context}/{id}/audit', methods: ['GET'], format: 'json', requirements: ['context' => 'payments|service_planning|forms|volunteering|children|giving|attendance|ministries|communications'])]
    public function audit(
        string $context,
        string $id,
        GetWorkflowAuditHandler $reader,
        #[MapQueryString]
        AuditFilter $filter = new AuditFilter(),
    ): JsonResponse {
        $this->denyAccessUnlessGranted($context.'.manage');

        return $this->result(fn () => $reader(new GetWorkflowAuditQuery($context, $id, $filter->limit, $filter->before)));
    }

    #[Route('/api/audit/{context}/{id}', methods: ['GET'], format: 'json', requirements: ['context' => 'identity|people|families|life_events|events|attendance|children|ministries|volunteering|service_planning|giving|payments|communications|reporting|forms|media'])]
    public function fieldHistory(
        string $context,
        string $id,
        GetFieldHistoryHandler $reader,
        #[MapQueryString(validationFailedStatusCode: 422)]
        AuditFilter $filter = new AuditFilter(),
    ): JsonResponse {
        $permission = match ($context) {
            'identity', 'media' => 'identity.manage',
            'reporting' => 'reports.manage',
            default => $context.'.manage',
        };
        $this->denyAccessUnlessGranted($permission);

        return $this->result(fn () => $reader(new GetFieldHistoryQuery($context, $id, $filter->limit, $filter->before)));
    }
}
