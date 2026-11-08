<?php

declare(strict_types=1);

namespace App\Communities\Presentation;

use App\Communities\Application\Command\CompleteCommunityFollowUp\CompleteCommunityFollowUpCommand;
use App\Communities\Application\Command\CreateCommunityFollowUp\CreateCommunityFollowUpCommand;
use App\Communities\Application\Query\ListCommunityFollowUps\ListCommunityFollowUpsHandler;
use App\Communities\Application\Query\ListCommunityFollowUps\ListCommunityFollowUpsQuery;
use App\Communities\Presentation\Request\CompleteCommunityFollowUpRequest;
use App\Communities\Presentation\Request\CreateCommunityFollowUpRequest;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Domain\CalendarDate;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Member follow-ups: managers anywhere, leaders in the communities they currently lead (Java `hasAnyAuthority`). */
#[IsGranted('ministries.manage|ministries.lead')]
final readonly class CommunityFollowUpsController
{
    public function __construct(
        private AuthenticatedAccount $account,
        private CommandBus $commands,
        private ListCommunityFollowUpsHandler $list,
    ) {
    }

    #[Route('/api/communities/{communityId}/follow-ups', methods: ['POST'])]
    public function create(#[PathUuid] string $communityId, #[MapRequestPayload] CreateCommunityFollowUpRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new CreateCommunityFollowUpCommand(
            $this->account->requireAccount(),
            CommunitiesJson::uuid($request->requestId),
            $communityId,
            (string) CommunitiesJson::uuid($request->personId),
            $request->note,
            null === $request->dueOn ? null : CalendarDate::parse($request->dueOn),
        ));

        return new JsonResponse(['id' => $id], 201);
    }

    #[Route('/api/communities/follow-ups/{followUpId}/complete', methods: ['POST'])]
    public function complete(#[PathUuid] string $followUpId, #[MapRequestPayload] CompleteCommunityFollowUpRequest $request): Response
    {
        $this->commands->dispatch(new CompleteCommunityFollowUpCommand($this->account->requireAccount(), $followUpId, $request->expectedVersion, $request->reason));

        return new Response(status: 204);
    }

    #[Route('/api/communities/{communityId}/follow-ups', methods: ['GET'])]
    public function list(
        #[PathUuid] string $communityId,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_BOOL, validationFailedStatusCode: 400)] bool $completed = false,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return new JsonResponse(array_map(CommunitiesJson::followUp(...), $this->list->handle(new ListCommunityFollowUpsQuery($communityId, $completed, $limit, $offset))));
    }
}
