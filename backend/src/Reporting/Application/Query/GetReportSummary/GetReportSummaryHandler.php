<?php

declare(strict_types=1);

namespace App\Reporting\Application\Query\GetReportSummary;

use App\Reporting\Application\Query\BuildReportSummary\BuildReportSummaryHandler;
use App\Reporting\Application\Query\BuildReportSummary\BuildReportSummaryQuery;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Security\AccessControl;

final readonly class GetReportSummaryHandler
{
    public function __construct(private AccessControl $access, private BuildReportSummaryHandler $buildReportSummaryHandler)
    {
    }

    public function __invoke(GetReportSummaryQuery $message): array
    {
        $from = $message->from;
        $to = $message->to;
        $this->requirePermissions(['reports.read']);
        $codes = array_values(array_filter(['people.read', 'attendance.manage', 'ministries.manage', 'giving.manage'], fn ($code) => $this->access->isGranted($code)));

        return ($this->buildReportSummaryHandler)(new BuildReportSummaryQuery($from, $to, $codes));
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
