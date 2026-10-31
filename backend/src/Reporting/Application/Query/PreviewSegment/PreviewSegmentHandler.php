<?php

declare(strict_types=1);

namespace App\Reporting\Application\Query\PreviewSegment;

use App\Reporting\Application\Query\EvaluateReportCriteria\EvaluateReportCriteriaHandler;
use App\Reporting\Application\Query\EvaluateReportCriteria\EvaluateReportCriteriaQuery;
use App\Reporting\Domain\Model\SegmentCriteria;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Security\AccessControl;

final readonly class PreviewSegmentHandler
{
    public function __construct(private AccessControl $access, private EvaluateReportCriteriaHandler $evaluateReportCriteriaHandler)
    {
    }

    public function __invoke(PreviewSegmentQuery $message): array
    {
        $filters = $message->filters;
        $this->requirePermissions(['reports.read', ...SegmentCriteria::permissions($filters)]);

        return ($this->evaluateReportCriteriaHandler)(new EvaluateReportCriteriaQuery($filters));
    }

    private function requirePermissions(array $codes): void
    {
        foreach ($codes as $code) {
            if (!$this->access->isGranted($code)) {
                throw new AccessDenied('Source permissions are required for this report or task.');
            }
        }
    }
}
