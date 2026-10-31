<?php

declare(strict_types=1);

namespace App\Giving\Presentation\Http\Controller;

use App\Giving\Application\Command\AllocatePledge\AllocatePledgeCommand;
use App\Giving\Application\Command\AllocatePledge\AllocatePledgeHandler;
use App\Giving\Application\Command\CancelPledgeAllocation\CancelPledgeAllocationCommand;
use App\Giving\Application\Command\CancelPledgeAllocation\CancelPledgeAllocationHandler;
use App\Giving\Application\Query\ExportGivingCsv\ExportGivingCsvHandler;
use App\Giving\Application\Query\ExportGivingCsv\ExportGivingCsvQuery;
use App\Giving\Application\Query\GetGivingReport\GetGivingReportHandler;
use App\Giving\Application\Query\GetGivingReport\GetGivingReportQuery;
use App\Giving\Application\Query\GetPledgeProgress\GetPledgeProgressHandler;
use App\Giving\Application\Query\GetPledgeProgress\GetPledgeProgressQuery;
use App\Giving\Presentation\Http\DTO\CancelPledgeAllocationPayload;
use App\Giving\Presentation\Http\DTO\PledgeAllocationPayload;
use App\Giving\Presentation\Http\DTO\ProgressFilter;
use App\Giving\Presentation\Http\DTO\ReportFilter;
use App\Shared\Presentation\Http\WorkflowController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/giving', format: 'json'), IsGranted('giving.manage')]
final class GivingReportsController extends WorkflowController
{
    public function __construct(
        private readonly GetGivingReportHandler $getGivingReportHandler,
        private readonly ExportGivingCsvHandler $exportGivingCsvHandler,
        private readonly GetPledgeProgressHandler $getPledgeProgressHandler,
        private readonly CancelPledgeAllocationHandler $cancelPledgeAllocationHandler,
        private readonly AllocatePledgeHandler $allocatePledgeHandler,
    ) {
    }

    #[Route('/reports', methods: ['GET'])]
    public function report(
        #[MapQueryString(validationFailedStatusCode: 422)]
        ReportFilter $filter,
    ): JsonResponse {
        return $this->confidential($this->result(fn () => ($this->getGivingReportHandler)(new GetGivingReportQuery($filter->from, $filter->to, $filter->donorId, $filter->fundId))));
    }

    #[Route('/reports/export', methods: ['GET'])]
    public function export(
        #[MapQueryString(validationFailedStatusCode: 422)]
        ReportFilter $filter,
    ): Response {
        $result = $this->result(fn () => ($this->getGivingReportHandler)(new GetGivingReportQuery($filter->from, $filter->to, $filter->donorId, $filter->fundId)));
        if (200 !== $result->getStatusCode()) {
            return $result;
        }

        return new Response(($this->exportGivingCsvHandler)(new ExportGivingCsvQuery(json_decode($result->getContent(), true, flags: JSON_THROW_ON_ERROR))), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="giving-report.csv"', 'Cache-Control' => 'no-store']);
    }

    #[Route('/pledge-progress', methods: ['GET'])]
    public function progress(
        #[MapQueryString(validationFailedStatusCode: 422)]
        ProgressFilter $filter,
    ): JsonResponse {
        return $this->confidential($this->result(fn () => ($this->getPledgeProgressHandler)(new GetPledgeProgressQuery($filter->asOf))));
    }

    #[Route('/pledge-allocations/{id}/cancel', methods: ['POST'])]
    public function cancel(
        string $id,
        #[MapRequestPayload]
        CancelPledgeAllocationPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->cancelPledgeAllocationHandler)(new CancelPledgeAllocationCommand($id, $p->expectedVersion, $p->reason)));
    }

    private function confidential(JsonResponse $response): JsonResponse
    {
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[Route('/pledge-allocations', methods: ['POST'])]
    public function allocate(
        #[MapRequestPayload]
        PledgeAllocationPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->allocatePledgeHandler)(new AllocatePledgeCommand($p->pledgeId, $p->giftId, $p->amountMinor, $p->reference)), 201);
    }
}
