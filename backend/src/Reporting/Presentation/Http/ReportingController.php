<?php

declare(strict_types=1);

namespace App\Reporting\Presentation\Http;

use App\Reporting\Application\Command\RunFollowupRuleManually\RunFollowupRuleManuallyCommand;
use App\Reporting\Application\Command\RunFollowupRuleManually\RunFollowupRuleManuallyHandler;
use App\Reporting\Application\Command\SaveFollowupRule\SaveFollowupRuleCommand;
use App\Reporting\Application\Command\SaveFollowupRule\SaveFollowupRuleHandler;
use App\Reporting\Application\Command\SaveSegment\SaveSegmentCommand;
use App\Reporting\Application\Command\SaveSegment\SaveSegmentHandler;
use App\Reporting\Application\Query\EvaluateSegment\EvaluateSegmentHandler;
use App\Reporting\Application\Query\EvaluateSegment\EvaluateSegmentQuery;
use App\Reporting\Application\Query\ExportReportCsv\ExportReportCsvHandler;
use App\Reporting\Application\Query\ExportReportCsv\ExportReportCsvQuery;
use App\Reporting\Application\Query\GetReportSummary\GetReportSummaryHandler;
use App\Reporting\Application\Query\GetReportSummary\GetReportSummaryQuery;
use App\Reporting\Application\Query\ListFollowupAssignees\ListFollowupAssigneesHandler;
use App\Reporting\Application\Query\ListFollowupAssignees\ListFollowupAssigneesQuery;
use App\Reporting\Application\Query\ListFollowupRules\ListFollowupRulesHandler;
use App\Reporting\Application\Query\ListFollowupRules\ListFollowupRulesQuery;
use App\Reporting\Application\Query\ListSegments\ListSegmentsHandler;
use App\Reporting\Application\Query\ListSegments\ListSegmentsQuery;
use App\Reporting\Application\Query\PreviewSegment\PreviewSegmentHandler;
use App\Reporting\Application\Query\PreviewSegment\PreviewSegmentQuery;
use App\Shared\Presentation\Http\WorkflowController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/reports', format: 'json')]
final class ReportingController extends WorkflowController
{
    public function __construct(
        private readonly GetReportSummaryHandler $getReportSummaryHandler,
        private readonly ListSegmentsHandler $listSegmentsHandler,
        private readonly SaveSegmentHandler $saveSegmentHandler,
        private readonly PreviewSegmentHandler $previewSegmentHandler,
        private readonly EvaluateSegmentHandler $evaluateSegmentHandler,
        private readonly ListFollowupAssigneesHandler $listFollowupAssigneesHandler,
        private readonly ListFollowupRulesHandler $listFollowupRulesHandler,
        private readonly SaveFollowupRuleHandler $saveFollowupRuleHandler,
        private readonly RunFollowupRuleManuallyHandler $runFollowupRuleManuallyHandler,
        private readonly ExportReportCsvHandler $exportReportCsvHandler,
    ) {
    }

    #[Route('/summary', methods: ['GET'])]
    public function summary(
        #[MapQueryString(validationFailedStatusCode: 422)]
        ReportQuery $q,
    ): JsonResponse {
        return $this->result(fn () => ($this->getReportSummaryHandler)(new GetReportSummaryQuery($q->from, $q->to)));
    }

    #[Route('/summary.csv', methods: ['GET'])]
    public function summaryCsv(
        #[MapQueryString(validationFailedStatusCode: 422)]
        ReportQuery $q,
    ): Response {
        return $this->download(function () use ($q) {
            $r = ($this->getReportSummaryHandler)(new GetReportSummaryQuery($q->from, $q->to));
            $rows = [];
            foreach ($r['sections']['people']['byStatus'] ?? [] as $status => $count) {
                $rows[] = ['section' => 'people', 'date' => '', 'label' => $status, 'currency' => '', 'value' => $count, 'unit' => 'people'];
            }
            foreach ($r['sections']['attendance']['days'] ?? [] as $d) {
                foreach (['present', 'absent', 'excused', 'visitors'] as $key) {
                    $rows[] = ['section' => 'attendance', 'date' => $d['date'], 'label' => $key, 'currency' => '', 'value' => $d[$key], 'unit' => 'occurrence entries'];
                }
            }
            foreach ($r['sections']['ministries']['items'] ?? [] as $g) {
                $rows[] = ['section' => 'ministries', 'date' => '', 'label' => $g['name'], 'currency' => '', 'value' => $g['members'], 'unit' => 'current active members'];
            }
            foreach ($r['sections']['giving']['totals'] ?? [] as $g) {
                $rows[] = ['section' => 'giving', 'date' => '', 'label' => 'net', 'currency' => $g['currency'], 'value' => $g['netAmountMinor'], 'unit' => 'minor currency units'];
            }

            return $rows;
        }, 'operational-report.csv');
    }

    #[Route('/segments', methods: ['GET'])]
    public function segments(): JsonResponse
    {
        return $this->result(fn () => ($this->listSegmentsHandler)(new ListSegmentsQuery()));
    }

    #[Route('/segments', methods: ['POST'])]
    public function createSegment(
        #[MapRequestPayload]
        SegmentPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->saveSegmentHandler)(new SaveSegmentCommand($p->name, $p->filters)), 201);
    }

    #[Route('/segments/{id}', methods: ['PUT'])]
    public function editSegment(
        string $id,
        #[MapRequestPayload]
        SegmentPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->saveSegmentHandler)(new SaveSegmentCommand($p->name, $p->filters, $id, $p->expectedVersion)));
    }

    #[Route('/segments/preview', methods: ['POST'])]
    public function preview(
        #[MapRequestPayload]
        PreviewPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->previewSegmentHandler)(new PreviewSegmentQuery($p->filters)));
    }

    #[Route('/segments/{id}/members', methods: ['GET'])]
    public function members(string $id): JsonResponse
    {
        return $this->result(fn () => ($this->evaluateSegmentHandler)(new EvaluateSegmentQuery($id)));
    }

    #[Route('/segments/{id}/members.csv', methods: ['GET'])]
    public function membersCsv(string $id): Response
    {
        return $this->download(fn () => ($this->evaluateSegmentHandler)(new EvaluateSegmentQuery($id))['items'], 'segment-members.csv');
    }

    #[Route('/assignees', methods: ['GET'])]
    public function assignees(
        #[MapQueryString(validationFailedStatusCode: 422)]
        AssigneeQuery $q = new AssigneeQuery(),
    ): JsonResponse {
        return $this->result(fn () => ($this->listFollowupAssigneesHandler)(new ListFollowupAssigneesQuery($q->segmentId, $q->taskId)));
    }

    #[Route('/rules', methods: ['GET'])]
    public function rules(): JsonResponse
    {
        return $this->result(fn () => ($this->listFollowupRulesHandler)(new ListFollowupRulesQuery()));
    }

    #[Route('/rules', methods: ['POST'])]
    public function createRule(
        #[MapRequestPayload]
        RulePayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->saveFollowupRuleHandler)(new SaveFollowupRuleCommand(array_diff_key(get_object_vars($p), ['expectedVersion' => true]))), 201);
    }

    #[Route('/rules/{id}', methods: ['PUT'])]
    public function editRule(
        string $id,
        #[MapRequestPayload]
        RulePayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->saveFollowupRuleHandler)(new SaveFollowupRuleCommand(array_diff_key(get_object_vars($p), ['expectedVersion' => true]), $id, $p->expectedVersion)));
    }

    #[Route('/rules/{id}/run', methods: ['POST'])]
    public function runRule(string $id): JsonResponse
    {
        return $this->result(fn () => ($this->runFollowupRuleManuallyHandler)(new RunFollowupRuleManuallyCommand($id)));
    }

    private function download(callable $rows, string $filename): Response
    {
        $response = $this->result($rows);
        if (200 !== $response->getStatusCode()) {
            return $response;
        }

        return new Response(($this->exportReportCsvHandler)(new ExportReportCsvQuery(json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR))), 200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="'.$filename.'"', 'Cache-Control' => 'private, no-store']);
    }

    protected function result(callable $operation, int $status = 200): JsonResponse
    {
        $r = parent::result($operation, $status);
        $r->headers->set('Cache-Control', 'private, no-store');
        $r->setVary('Authorization', false);

        return $r;
    }
}
