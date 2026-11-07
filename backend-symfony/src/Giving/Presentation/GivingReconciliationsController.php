<?php

declare(strict_types=1);

namespace App\Giving\Presentation;

use App\Giving\Application\Command\CreateReconciliation\CreateReconciliationCommand;
use App\Giving\Application\Command\ReviewReconciliation\ReviewReconciliationCommand;
use App\Giving\Application\Contract\ReconciliationItem;
use App\Giving\Application\Contract\ReconciliationReviewView;
use App\Giving\Application\Contract\ReconciliationSummary;
use App\Giving\Application\Query\GetReconciliation\GetReconciliationHandler;
use App\Giving\Application\Query\GetReconciliation\GetReconciliationQuery;
use App\Giving\Application\Query\ListReconciliations\ListReconciliationsHandler;
use App\Giving\Application\Query\ListReconciliations\ListReconciliationsQuery;
use App\Giving\Presentation\Request\CreateReconciliationRequest;
use App\Giving\Presentation\Request\ReviewReconciliationRequest;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Domain\CalendarDate;
use App\Shared\Presentation\Json;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Bank and cash reconciliation. Writes need giving.reconcile; reads need giving.read (implied by giving.manage). */
#[Route('/api/giving/reconciliations')]
final readonly class GivingReconciliationsController
{
    public function __construct(
        private CommandBus $commands,
        private AuthenticatedAccount $account,
        private ListReconciliationsHandler $list,
        private GetReconciliationHandler $get,
    ) {
    }

    #[IsGranted('giving.reconcile')]
    #[Route('', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateReconciliationRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new CreateReconciliationCommand(
            $this->account->requireAccount(),
            strtolower((string) $request->requestId),
            $request->reference,
            (int) $request->expectedMinor,
            $request->currency,
            CalendarDate::parse((string) $request->reconciledOn),
            $request->reason,
            array_map(static fn (mixed $id): string => strtolower((string) $id), $request->giftIds ?? []),
        ));

        return new JsonResponse(['id' => $id], 201);
    }

    #[IsGranted('giving.reconcile')]
    #[Route('/{id}/reviews', methods: ['POST'])]
    public function review(#[PathUuid] string $id, #[MapRequestPayload] ReviewReconciliationRequest $request): JsonResponse
    {
        $review = $this->commands->dispatch(new ReviewReconciliationCommand(
            $this->account->requireAccount(),
            strtolower((string) $request->requestId),
            $id,
            $request->expectedVersion,
            $request->outcome,
            $request->note,
        ));

        return new JsonResponse(['id' => $review], 201);
    }

    #[IsGranted('giving.read')]
    #[Route('', methods: ['GET'])]
    public function list(
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $page = $this->list->handle(new ListReconciliationsQuery($limit, $offset));

        return new JsonResponse(['items' => array_map(self::summary(...), $page->items), 'total' => $page->total]);
    }

    #[IsGranted('giving.read')]
    #[Route('/{id}', methods: ['GET'])]
    public function get(#[PathUuid] string $id): JsonResponse
    {
        $detail = $this->get->handle(new GetReconciliationQuery($id));

        return new JsonResponse([
            'summary' => self::summary($detail->summary),
            'items' => array_map(static fn (ReconciliationItem $item): array => [
                'giftId' => $item->giftId,
                'donorName' => $item->donorName,
                'receivedOn' => $item->receivedOn->iso(),
                'amountMinor' => $item->amountMinor,
                'reversed' => $item->reversed,
                'refundedMinor' => $item->refundedMinor,
            ], $detail->items),
            'reviews' => array_map(self::reviewView(...), $detail->reviews),
        ]);
    }

    /** @return array<string, mixed> */
    private static function summary(ReconciliationSummary $summary): array
    {
        $batch = $summary->batch;

        return [
            'batch' => [
                'id' => $batch->id,
                'reference' => $batch->reference,
                'expectedMinor' => $batch->expectedMinor,
                'actualMinor' => $batch->actualMinor,
                'currency' => $batch->currency,
                'reconciledOn' => $batch->reconciledOn->iso(),
                'reason' => $batch->reason,
                'createdBy' => $batch->createdBy,
                'createdAt' => Json::instant($batch->createdAt),
                'giftIds' => $batch->giftIds,
            ],
            'reversedMinor' => $summary->reversedMinor,
            'currentNetMinor' => $summary->currentNetMinor,
            'version' => $summary->version,
            'review' => null === $summary->review ? null : self::reviewView($summary->review),
            'needsReview' => $summary->needsReview,
        ];
    }

    /** @return array<string, mixed> */
    private static function reviewView(ReconciliationReviewView $review): array
    {
        return [
            'id' => $review->id,
            'batchId' => $review->batchId,
            'version' => $review->version,
            'outcome' => $review->outcome,
            'note' => $review->note,
            'reversedMinor' => $review->reversedMinor,
            'createdBy' => $review->createdBy,
            'createdAt' => Json::instant($review->createdAt),
        ];
    }
}
