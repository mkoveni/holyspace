<?php

declare(strict_types=1);

namespace App\Reporting\Application\Query\EvaluateSegment;

use App\Reporting\Application\Query\EvaluateReportCriteria\EvaluateReportCriteriaHandler;
use App\Reporting\Application\Query\EvaluateReportCriteria\EvaluateReportCriteriaQuery;
use App\Reporting\Application\Query\GetSegment\GetSegmentHandler;
use App\Reporting\Application\Query\GetSegment\GetSegmentQuery;

final readonly class EvaluateSegmentHandler
{
    public function __construct(private GetSegmentHandler $getSegmentHandler, private EvaluateReportCriteriaHandler $evaluateReportCriteriaHandler)
    {
    }

    public function __invoke(EvaluateSegmentQuery $message): array
    {
        $id = $message->id;
        $s = ($this->getSegmentHandler)(new GetSegmentQuery($id));

        return ($this->evaluateReportCriteriaHandler)(new EvaluateReportCriteriaQuery($s->filters)) + ['segment' => $s];
    }
}
