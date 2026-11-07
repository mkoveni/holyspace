<?php

declare(strict_types=1);

namespace App\Giving\Presentation;

use App\Giving\Application\Command\RecordGift\RecordGiftCommand;
use App\Giving\Application\Command\ReverseGift\ReverseGiftCommand;
use App\Giving\Application\Contract\GiftDetail;
use App\Giving\Application\Contract\GiftFilter;
use App\Giving\Application\Contract\GiftHistoryEntry;
use App\Giving\Application\Contract\GiftSummary;
use App\Giving\Application\Query\GetGift\GetGiftHandler;
use App\Giving\Application\Query\GetGift\GetGiftQuery;
use App\Giving\Application\Query\ListGifts\ListGiftsHandler;
use App\Giving\Application\Query\ListGifts\ListGiftsQuery;
use App\Giving\Domain\Allocation;
use App\Giving\Presentation\Request\RecordGiftRequest;
use App\Giving\Presentation\Request\ReverseGiftRequest;
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

/** Manual gift ledger. Reads need giving.read (implied by giving.manage); posting needs giving.manage; reversals need giving.reverse. */
#[Route('/api/giving/gifts')]
final readonly class GivingGiftsController
{
    public function __construct(
        private CommandBus $commands,
        private AuthenticatedAccount $account,
        private ListGiftsHandler $list,
        private GetGiftHandler $get,
    ) {
    }

    #[IsGranted('giving.read')]
    #[Route('', methods: ['GET'])]
    public function list(
        #[MapQueryParameter(validationFailedStatusCode: 400)] string $from,
        #[MapQueryParameter(validationFailedStatusCode: 400)] string $to,
        #[MapQueryParameter] ?string $currency = null,
        #[MapQueryParameter] ?string $fundId = null,
        #[MapQueryParameter] ?string $donorId = null,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $page = $this->list->handle(new ListGiftsQuery(new GiftFilter(
            CalendarDate::parse($from),
            CalendarDate::parse($to),
            $currency,
            null === $fundId ? null : Json::uuid($fundId),
            null === $donorId ? null : Json::uuid($donorId),
            $limit,
            $offset,
        )));

        return new JsonResponse([
            'items' => array_map(static fn (GiftSummary $gift): array => [
                'id' => $gift->id,
                'donorId' => $gift->donorId,
                'donorName' => $gift->donorName,
                'amountMinor' => $gift->amountMinor,
                'currency' => $gift->currency,
                'receivedOn' => $gift->receivedOn->iso(),
                'method' => $gift->method,
                'externalReference' => $gift->externalReference,
                'reversed' => $gift->reversed,
                'reconciled' => $gift->reconciled,
                'refundedMinor' => $gift->refundedMinor,
            ], $page->items),
            'total' => $page->total,
        ]);
    }

    #[IsGranted('giving.read')]
    #[Route('/{id}', methods: ['GET'])]
    public function get(#[PathUuid] string $id): JsonResponse
    {
        return new JsonResponse(self::detail($this->get->handle(new GetGiftQuery($id))));
    }

    #[IsGranted('giving.manage')]
    #[Route('', methods: ['POST'])]
    public function record(#[MapRequestPayload] RecordGiftRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new RecordGiftCommand(
            $this->account->requireAccount(),
            strtolower((string) $request->requestId),
            null === $request->donorId ? null : strtolower($request->donorId),
            (int) $request->amountMinor,
            $request->currency,
            CalendarDate::parse((string) $request->receivedOn),
            $request->method,
            $request->externalReference,
            array_map(
                static fn (mixed $item): Allocation => new Allocation(strtolower((string) $item['fundId']), (int) $item['amountMinor']),
                $request->allocations ?? [],
            ),
        ));

        return new JsonResponse(['id' => $id], 201);
    }

    #[IsGranted('giving.reverse')]
    #[Route('/{id}/reversals', methods: ['POST'])]
    public function reverse(#[PathUuid] string $id, #[MapRequestPayload] ReverseGiftRequest $request): JsonResponse
    {
        $reversal = $this->commands->dispatch(new ReverseGiftCommand(
            $this->account->requireAccount(),
            strtolower((string) $request->requestId),
            $id,
            CalendarDate::parse((string) $request->reversedOn),
            $request->reason,
        ));

        return new JsonResponse(['id' => $reversal], 201);
    }

    /**
     * Java serialises GivingReader.Detail with the gift as a nested record.
     *
     * @return array<string, mixed>
     */
    private static function detail(GiftDetail $detail): array
    {
        $reversal = $detail->reversal;

        return [
            'gift' => [
                'id' => $detail->id,
                'donorId' => $detail->donorId,
                'money' => ['amountMinor' => $detail->money->amountMinor, 'currency' => $detail->money->currency],
                'receivedOn' => $detail->receivedOn->iso(),
                'method' => $detail->method,
                'externalReference' => $detail->externalReference,
                'allocations' => array_map(
                    static fn (Allocation $a): array => ['fundId' => $a->fundId, 'amountMinor' => $a->amountMinor],
                    $detail->allocations,
                ),
                'createdBy' => $detail->createdBy,
                'createdAt' => Json::instant($detail->createdAt),
            ],
            'donorName' => $detail->donorName,
            'fundNames' => (object) $detail->fundNames,
            'reversal' => null === $reversal ? null : [
                'id' => $reversal->id,
                'giftId' => $reversal->giftId,
                'reversedOn' => $reversal->reversedOn->iso(),
                'reason' => $reversal->reason,
                'createdBy' => $reversal->createdBy,
                'createdAt' => Json::instant($reversal->createdAt),
            ],
            'history' => array_map(static fn (GiftHistoryEntry $entry): array => [
                'action' => $entry->action,
                'reason' => $entry->reason,
                'actorId' => $entry->actorId,
                'occurredAt' => Json::instant($entry->occurredAt),
            ], $detail->history),
            'refundedMinor' => $detail->refundedMinor,
        ];
    }
}
