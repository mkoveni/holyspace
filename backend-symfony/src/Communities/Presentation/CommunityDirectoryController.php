<?php

declare(strict_types=1);

namespace App\Communities\Presentation;

use App\Communities\Application\Command\AppointCommunityLeader\AppointCommunityLeaderCommand;
use App\Communities\Application\Command\CreateCommunity\CreateCommunityCommand;
use App\Communities\Application\Command\EndCommunityLeadership\EndCommunityLeadershipCommand;
use App\Communities\Application\Command\JoinCommunity\JoinCommunityCommand;
use App\Communities\Application\Command\LeaveCommunity\LeaveCommunityCommand;
use App\Communities\Application\Command\UpdateCommunity\UpdateCommunityCommand;
use App\Communities\Application\Query\GetCommunity\GetCommunityHandler;
use App\Communities\Application\Query\GetCommunity\GetCommunityQuery;
use App\Communities\Application\Query\GetCommunityHistory\GetCommunityHistoryHandler;
use App\Communities\Application\Query\GetCommunityHistory\GetCommunityHistoryQuery;
use App\Communities\Application\Query\ListCommunities\ListCommunitiesHandler;
use App\Communities\Application\Query\ListCommunities\ListCommunitiesQuery;
use App\Communities\Application\Query\ListCommunityLeadership\ListCommunityLeadershipHandler;
use App\Communities\Application\Query\ListCommunityLeadership\ListCommunityLeadershipQuery;
use App\Communities\Application\Query\ListCommunityMembers\ListCommunityMembersHandler;
use App\Communities\Application\Query\ListCommunityMembers\ListCommunityMembersQuery;
use App\Communities\Application\Query\ListPersonCommunities\ListPersonCommunitiesHandler;
use App\Communities\Application\Query\ListPersonCommunities\ListPersonCommunitiesQuery;
use App\Communities\Presentation\Request\AppointCommunityLeaderRequest;
use App\Communities\Presentation\Request\CreateCommunityRequest;
use App\Communities\Presentation\Request\EndCommunityLeadershipRequest;
use App\Communities\Presentation\Request\JoinCommunityRequest;
use App\Communities\Presentation\Request\LeaveCommunityRequest;
use App\Communities\Presentation\Request\UpdateCommunityRequest;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Ministry and group directory, membership and leadership. Any of ministries.read, .manage or .lead opens the
 * controller (Java `hasAnyAuthority`); the handlers apply the real rules, including current leadership scope.
 */
#[IsGranted('ministries.read|ministries.manage|ministries.lead')]
final readonly class CommunityDirectoryController
{
    public function __construct(
        private AuthenticatedAccount $account,
        private CommandBus $commands,
        private ListCommunitiesHandler $list,
        private GetCommunityHandler $get,
        private ListCommunityMembersHandler $members,
        private ListCommunityLeadershipHandler $leadership,
        private GetCommunityHistoryHandler $history,
        private ListPersonCommunitiesHandler $personCommunities,
    ) {
    }

    #[Route('/api/communities/people/{personId}', methods: ['GET'])]
    public function personCommunities(
        #[PathUuid] string $personId,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return new JsonResponse(array_map(CommunitiesJson::participation(...), $this->personCommunities->handle(new ListPersonCommunitiesQuery($personId, $limit, $offset))));
    }

    #[Route('/api/communities', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateCommunityRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new CreateCommunityCommand(
            $this->account->requireAccount(),
            CommunitiesJson::uuid($request->requestId),
            (string) $request->kind,
            $request->name,
            $request->description,
            CommunitiesJson::uuid($request->parentId),
            $request->campus,
        ));

        return new JsonResponse(['id' => $id], 201);
    }

    #[Route('/api/communities/{communityId}', methods: ['PUT'])]
    public function update(#[PathUuid] string $communityId, #[MapRequestPayload] UpdateCommunityRequest $request): Response
    {
        $this->commands->dispatch(new UpdateCommunityCommand(
            $this->account->requireAccount(),
            $communityId,
            $request->expectedVersion,
            $request->name,
            $request->description,
            CommunitiesJson::uuid($request->parentId),
            $request->campus,
            $request->active,
            $request->reason,
        ));

        return new Response(status: 204);
    }

    #[Route('/api/communities/{communityId}/members', methods: ['POST'])]
    public function join(#[PathUuid] string $communityId, #[MapRequestPayload] JoinCommunityRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new JoinCommunityCommand(
            $this->account->requireAccount(),
            CommunitiesJson::uuid($request->requestId),
            $communityId,
            (string) CommunitiesJson::uuid($request->personId),
        ));

        return new JsonResponse(['id' => $id], 201);
    }

    #[Route('/api/communities/memberships/{membershipId}/leave', methods: ['POST'])]
    public function leave(#[PathUuid] string $membershipId, #[MapRequestPayload] LeaveCommunityRequest $request): Response
    {
        $this->commands->dispatch(new LeaveCommunityCommand($this->account->requireAccount(), $membershipId, $request->expectedVersion, $request->reason));

        return new Response(status: 204);
    }

    #[Route('/api/communities/memberships/{membershipId}/leadership', methods: ['POST'])]
    public function appoint(#[PathUuid] string $membershipId, #[MapRequestPayload] AppointCommunityLeaderRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new AppointCommunityLeaderCommand(
            $this->account->requireAccount(),
            CommunitiesJson::uuid($request->requestId),
            $membershipId,
            $request->expectedVersion,
            (string) $request->role,
            CommunitiesJson::uuid($request->accountId),
            $request->reason,
        ));

        return new JsonResponse(['id' => $id], 201);
    }

    #[Route('/api/communities/leadership/{leadershipId}/end', methods: ['POST'])]
    public function endLeadership(#[PathUuid] string $leadershipId, #[MapRequestPayload] EndCommunityLeadershipRequest $request): Response
    {
        $this->commands->dispatch(new EndCommunityLeadershipCommand($this->account->requireAccount(), $leadershipId, $request->expectedVersion, $request->reason));

        return new Response(status: 204);
    }

    #[Route('/api/communities', methods: ['GET'])]
    public function list(
        #[MapQueryParameter] ?string $kind = null,
        #[MapQueryParameter] ?string $search = null,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_BOOL, validationFailedStatusCode: 400)] bool $archived = false,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $page = $this->list->handle(new ListCommunitiesQuery($kind, $search, $archived, $limit, $offset));

        return new JsonResponse(['communities' => array_map(CommunitiesJson::community(...), $page->communities), 'total' => $page->total]);
    }

    #[Route('/api/communities/{communityId}', methods: ['GET'])]
    public function get(#[PathUuid] string $communityId): JsonResponse
    {
        $details = $this->get->handle(new GetCommunityQuery($communityId));

        return new JsonResponse(['community' => CommunitiesJson::community($details->community), 'canOperate' => $details->canOperate]);
    }

    #[Route('/api/communities/{communityId}/members', methods: ['GET'])]
    public function members(
        #[PathUuid] string $communityId,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_BOOL, validationFailedStatusCode: 400)] bool $ended = false,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $page = $this->members->handle(new ListCommunityMembersQuery($communityId, $ended, $limit, $offset));

        return new JsonResponse(['members' => array_map(CommunitiesJson::member(...), $page->members), 'total' => $page->total]);
    }

    #[Route('/api/communities/{communityId}/leadership', methods: ['GET'])]
    public function leadership(
        #[PathUuid] string $communityId,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return new JsonResponse(array_map(CommunitiesJson::appointment(...), $this->leadership->handle(new ListCommunityLeadershipQuery($communityId, $limit, $offset))));
    }

    #[Route('/api/communities/{communityId}/history', methods: ['GET'])]
    public function history(
        #[PathUuid] string $communityId,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return new JsonResponse(array_map(CommunitiesJson::history(...), $this->history->handle(new GetCommunityHistoryQuery($communityId, $limit, $offset))));
    }
}
