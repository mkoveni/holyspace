<?php

declare(strict_types=1);

namespace App\Giving\Presentation;

use App\Giving\Application\Command\AssignPledgeGift\AssignPledgeGiftCommand;
use App\Giving\Application\Command\CancelPledge\CancelPledgeCommand;
use App\Giving\Application\Command\CancelPledgeAssignment\CancelPledgeAssignmentCommand;
use App\Giving\Application\Command\CreatePledge\CreatePledgeCommand;
use App\Giving\Application\Contract\HistoryEntry;
use App\Giving\Application\Contract\PledgeAssignmentEntry;
use App\Giving\Application\Contract\PledgeCandidate;
use App\Giving\Application\Contract\PledgeSummary;
use App\Giving\Application\Query\GetPledge\GetPledgeHandler;
use App\Giving\Application\Query\GetPledge\GetPledgeQuery;
use App\Giving\Application\Query\ListPledgeGiftCandidates\ListPledgeGiftCandidatesHandler;
use App\Giving\Application\Query\ListPledgeGiftCandidates\ListPledgeGiftCandidatesQuery;
use App\Giving\Application\Query\ListPledges\ListPledgesHandler;
use App\Giving\Application\Query\ListPledges\ListPledgesQuery;
use App\Giving\Presentation\Request\AssignPledgeGiftRequest;
use App\Giving\Presentation\Request\CancelPledgeRequest;
use App\Giving\Presentation\Request\CreatePledgeRequest;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Domain\CalendarDate;
use App\Shared\Presentation\Json;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Pledges and their explicit gift assignments. Reads need giving.read (implied by giving.manage); writes need giving.manage. */
final readonly class GivingPledgesController
{
    public function __construct(
        private CommandBus $commands,
        private AuthenticatedAccount $account,
        private ListPledgesHandler $list,
        private GetPledgeHandler $get,
        private ListPledgeGiftCandidatesHandler $candidates,
    ) {
    }

    #[IsGranted('giving.manage')]
    #[Route('/api/giving/pledges', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreatePledgeRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new CreatePledgeCommand(
            $this->account->requireAccount(),
            strtolower((string) $request->requestId),
            strtolower((string) $request->donorId),
            strtolower((string) $request->fundId),
            (int) $request->amountMinor,
            $request->currency,
            CalendarDate::parse((string) $request->startsOn),
            CalendarDate::parse((string) $request->endsOn),
        ));

        return new JsonResponse(['id' => $id], 201);
    }

    #[IsGranted('giving.manage')]
    #[Route('/api/giving/pledges/{id}/cancel', methods: ['POST'])]
    public function cancel(#[PathUuid] string $id, #[MapRequestPayload] CancelPledgeRequest $request): Response
    {
        $this->commands->dispatch(new CancelPledgeCommand($this->account->requireAccount(), $id, $request->expectedVersion, $request->reason));

        return new Response(status: 204);
    }

    #[IsGranted('giving.manage')]
    #[Route('/api/giving/pledges/{id}/assignments', methods: ['POST'])]
    public function assign(#[PathUuid] string $id, #[MapRequestPayload] AssignPledgeGiftRequest $request): JsonResponse
    {
        $assignment = $this->commands->dispatch(new AssignPledgeGiftCommand(
            $this->account->requireAccount(),
            strtolower((string) $request->requestId),
            $id,
            strtolower((string) $request->giftId),
            $request->expectedVersion,
            (int) $request->amountMinor,
        ));

        return new JsonResponse(['id' => $assignment], 201);
    }

    #[IsGranted('giving.manage')]
    #[Route('/api/giving/pledge-assignments/{id}/cancel', methods: ['POST'])]
    public function cancelAssignment(#[PathUuid] string $id, #[MapRequestPayload] CancelPledgeRequest $request): Response
    {
        $this->commands->dispatch(new CancelPledgeAssignmentCommand($this->account->requireAccount(), $id, $request->expectedVersion, $request->reason));

        return new Response(status: 204);
    }

    #[IsGranted('giving.read')]
    #[Route('/api/giving/pledges', methods: ['GET'])]
    public function list(
        #[MapQueryParameter] ?string $donorId = null,
        #[MapQueryParameter] ?string $status = null,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $page = $this->list->handle(new ListPledgesQuery(null === $donorId ? null : Json::uuid($donorId), $status, $limit, $offset));

        return new JsonResponse([
            'items' => array_map(self::summary(...), $page->items),
            'total' => $page->total,
        ]);
    }

    #[IsGranted('giving.read')]
    #[Route('/api/giving/pledges/{id}', methods: ['GET'])]
    public function get(
        #[PathUuid] string $id,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $detail = $this->get->handle(new GetPledgeQuery($id, $limit, $offset));

        return new JsonResponse([
            'summary' => self::summary($detail->summary),
            'assignments' => array_map(static function (PledgeAssignmentEntry $entry): array {
                $a = $entry->assignment;

                return [
                    'assignment' => [
                        'id' => $a->id,
                        'pledgeId' => $a->pledgeId,
                        'giftId' => $a->giftId,
                        'fundId' => $a->fundId,
                        'donorId' => $a->donorId,
                        'currency' => $a->currency,
                        'amountMinor' => $a->amountMinor,
                        'status' => $a->status,
                        'version' => $a->version,
                        'cancellationReason' => $a->cancellationReason,
                        'cancelledBy' => $a->cancelledBy,
                        'cancelledAt' => null === $a->cancelledAt ? null : Json::instant($a->cancelledAt),
                        'createdBy' => $a->createdBy,
                        'createdAt' => Json::instant($a->createdAt),
                    ],
                    'receivedOn' => $entry->receivedOn->iso(),
                    'giftReversed' => $entry->giftReversed,
                ];
            }, $detail->assignments),
            'history' => array_map(static fn (HistoryEntry $entry): array => [
                'action' => $entry->action,
                'reason' => $entry->reason,
                'actorId' => $entry->actorId,
                'occurredAt' => Json::instant($entry->occurredAt),
            ], $detail->history),
        ]);
    }

    #[IsGranted('giving.read')]
    #[Route('/api/giving/pledges/{id}/candidates', methods: ['GET'])]
    public function candidates(
        #[PathUuid] string $id,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return new JsonResponse(array_map(static fn (PledgeCandidate $candidate): array => [
            'giftId' => $candidate->giftId,
            'receivedOn' => $candidate->receivedOn->iso(),
            'availableMinor' => $candidate->availableMinor,
            'reference' => $candidate->reference,
            'method' => $candidate->method,
        ], $this->candidates->handle(new ListPledgeGiftCandidatesQuery($id, $limit, $offset))));
    }

    /**
     * Java serialises PledgeReader.Summary with the pledge as a nested record.
     *
     * @return array<string, mixed>
     */
    private static function summary(PledgeSummary $summary): array
    {
        $p = $summary->pledge;

        return [
            'pledge' => [
                'id' => $p->id,
                'donorId' => $p->donorId,
                'fundId' => $p->fundId,
                'target' => ['amountMinor' => $p->target->amountMinor, 'currency' => $p->target->currency],
                'startsOn' => $p->startsOn->iso(),
                'endsOn' => $p->endsOn->iso(),
                'status' => $p->status,
                'version' => $p->version,
                'cancellationReason' => $p->cancellationReason,
                'createdBy' => $p->createdBy,
                'createdAt' => Json::instant($p->createdAt),
            ],
            'donorName' => $summary->donorName,
            'fundName' => $summary->fundName,
            'creditedMinor' => $summary->creditedMinor,
            'remainingMinor' => $summary->remainingMinor,
            'fulfilled' => $summary->fulfilled,
        ];
    }
}
