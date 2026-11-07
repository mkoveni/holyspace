<?php

declare(strict_types=1);

namespace App\Giving\Presentation;

use App\Giving\Application\Contract\GiftFilter;
use App\Giving\Application\Contract\GivingTotal;
use App\Giving\Application\Contract\LedgerLine;
use App\Giving\Application\Contract\LedgerReport;
use App\Giving\Application\Query\ExportGivingLedger\ExportGivingLedgerHandler;
use App\Giving\Application\Query\ExportGivingLedger\ExportGivingLedgerQuery;
use App\Giving\Application\Query\GetDonorStatement\GetDonorStatementHandler;
use App\Giving\Application\Query\GetDonorStatement\GetDonorStatementQuery;
use App\Giving\Application\Query\GetGivingTotals\GetGivingTotalsHandler;
use App\Giving\Application\Query\GetGivingTotals\GetGivingTotalsQuery;
use App\Shared\Domain\CalendarDate;
use App\Shared\Presentation\Json;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Period totals, donor statements and the ledger CSV. Totals and statements need giving.read (implied by giving.manage);
 * the export also needs giving.export. Reports cover the whole period, at most 5,000 allocation lines, never truncated.
 */
#[Route('/api/giving')]
final readonly class GivingReportsController
{
    public function __construct(
        private GetGivingTotalsHandler $totals,
        private GetDonorStatementHandler $statement,
        private ExportGivingLedgerHandler $export,
    ) {
    }

    #[IsGranted('giving.read')]
    #[Route('/totals', methods: ['GET'])]
    public function totals(
        #[MapQueryParameter(validationFailedStatusCode: 400)] string $from,
        #[MapQueryParameter(validationFailedStatusCode: 400)] string $to,
        #[MapQueryParameter] ?string $currency = null,
        #[MapQueryParameter] ?string $fundId = null,
        #[MapQueryParameter] ?string $donorId = null,
    ): JsonResponse {
        return new JsonResponse(array_map(
            self::total(...),
            $this->totals->handle(new GetGivingTotalsQuery(self::filter($from, $to, $currency, $fundId, $donorId))),
        ));
    }

    #[IsGranted('giving.read')]
    #[Route('/statements/{donorId}', methods: ['GET'])]
    public function statement(
        #[PathUuid] string $donorId,
        #[MapQueryParameter(validationFailedStatusCode: 400)] string $from,
        #[MapQueryParameter(validationFailedStatusCode: 400)] string $to,
    ): JsonResponse {
        $report = $this->statement->handle(new GetDonorStatementQuery($donorId, CalendarDate::parse($from), CalendarDate::parse($to)));

        return new JsonResponse([
            'donorId' => $report->donorId,
            'donorName' => $report->donorName,
            'from' => $report->from->iso(),
            'to' => $report->to->iso(),
            'generatedAt' => Json::instant($report->generatedAt),
            'lines' => array_map(self::line(...), $report->lines),
            'totals' => array_map(self::total(...), $report->totals),
        ]);
    }

    #[IsGranted('giving.export')]
    #[IsGranted('giving.read')]
    #[Route('/ledger.csv', methods: ['GET'])]
    public function ledger(
        #[MapQueryParameter(validationFailedStatusCode: 400)] string $from,
        #[MapQueryParameter(validationFailedStatusCode: 400)] string $to,
        #[MapQueryParameter] ?string $currency = null,
        #[MapQueryParameter] ?string $fundId = null,
        #[MapQueryParameter] ?string $donorId = null,
    ): Response {
        $report = $this->export->handle(new ExportGivingLedgerQuery(self::filter($from, $to, $currency, $fundId, $donorId)));

        return self::csv($report);
    }

    private static function csv(LedgerReport $report): Response
    {
        return new Response(GivingCsv::render($report), 200, [
            'Content-Type' => 'text/csv;charset=UTF-8',
            'Content-Disposition' => \sprintf('attachment; filename="giving-ledger-%s-%s.csv"', $report->from->iso(), $report->to->iso()),
            'Cache-Control' => 'no-store',
        ]);
    }

    /** The controller passes Java's fixed page (25, 0): reports ignore paging but the filter still validates it. */
    private static function filter(string $from, string $to, ?string $currency, ?string $fundId, ?string $donorId): GiftFilter
    {
        return new GiftFilter(
            CalendarDate::parse($from),
            CalendarDate::parse($to),
            $currency,
            null === $fundId ? null : Json::uuid($fundId),
            null === $donorId ? null : Json::uuid($donorId),
            25,
            0,
        );
    }

    /** @return array<string, string> */
    private static function total(GivingTotal $total): array
    {
        return [
            'currency' => $total->currency,
            'grossMinor' => $total->grossMinor,
            'reversedMinor' => $total->reversedMinor,
            'netMinor' => $total->netMinor,
        ];
    }

    /** @return array<string, mixed> */
    private static function line(LedgerLine $line): array
    {
        return [
            'giftId' => $line->giftId,
            'kind' => $line->kind,
            'date' => $line->date->iso(),
            'donorId' => $line->donorId,
            'donorName' => $line->donorName,
            'fundId' => $line->fundId,
            'fundName' => $line->fundName,
            'currency' => $line->currency,
            'amountMinor' => $line->amountMinor,
            'method' => $line->method,
            'reference' => $line->reference,
            'reason' => $line->reason,
        ];
    }
}
