<?php

declare(strict_types=1);

namespace App\Communities\Presentation;

use App\Communities\Application\Contract\MemberCommunity;
use App\Communities\Application\Contract\MemberCommunityLeader;
use App\Communities\Application\Contract\MemberGroupMeeting;
use App\Communities\Application\Query\GetMyCommunity\GetMyCommunityHandler;
use App\Communities\Application\Query\GetMyCommunity\GetMyCommunityQuery;
use App\Communities\Application\Query\GetMyGroupMeeting\GetMyGroupMeetingHandler;
use App\Communities\Application\Query\GetMyGroupMeeting\GetMyGroupMeetingQuery;
use App\Communities\Application\Query\ListMyCommunities\ListMyCommunitiesHandler;
use App\Communities\Application\Query\ListMyCommunities\ListMyCommunitiesQuery;
use App\Communities\Application\Query\ListMyGroupMeetings\ListMyGroupMeetingsHandler;
use App\Communities\Application\Query\ListMyGroupMeetings\ListMyGroupMeetingsQuery;
use App\Shared\Presentation\ImageUrl;
use App\Shared\Presentation\Json;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A member's own groups and ministries (Java `MemberCommunitiesController`). No staff permission is involved: the
 * handlers resolve the caller's verified person and never accept a person id; `access_control` requires a session
 * (Java `isAuthenticated()`). Responses are `no-store`.
 */
final readonly class MemberCommunitiesController
{
    public function __construct(
        private ListMyCommunitiesHandler $list,
        private GetMyCommunityHandler $detail,
        private ListMyGroupMeetingsHandler $meetings,
        private GetMyGroupMeetingHandler $meeting,
    ) {
    }

    #[Route('/api/member/communities', methods: ['GET'])]
    public function list(
        #[MapQueryParameter] ?string $kind = null,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $page = $this->list->handle(new ListMyCommunitiesQuery($kind, $limit, $offset));

        return self::private(['verified' => $page->verified, 'items' => array_map(self::community(...), $page->items), 'hasMore' => $page->hasMore]);
    }

    #[Route('/api/member/communities/{id}', methods: ['GET'])]
    public function detail(
        #[PathUuid] string $id,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $leaderLimit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $leaderOffset = 0,
    ): JsonResponse {
        $details = $this->detail->handle(new GetMyCommunityQuery($id, $leaderLimit, $leaderOffset));

        return self::private([
            'community' => self::community($details->community),
            'leaders' => array_map(static fn (MemberCommunityLeader $l): array => ['name' => $l->name, 'role' => $l->role], $details->leaders),
            'hasMoreLeaders' => $details->hasMoreLeaders,
        ]);
    }

    #[Route('/api/member/group-meetings', methods: ['GET'])]
    public function meetings(
        #[MapQueryParameter] ?string $communityId = null,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $page = $this->meetings->handle(new ListMyGroupMeetingsQuery(null === $communityId ? null : Json::uuid($communityId), $limit, $offset));

        return self::private(['verified' => $page->verified, 'items' => array_map(self::meetingJson(...), $page->items), 'hasMore' => $page->hasMore]);
    }

    #[Route('/api/member/group-meetings/{id}', methods: ['GET'])]
    public function meeting(#[PathUuid] string $id): JsonResponse
    {
        return self::private(self::meetingJson($this->meeting->handle(new GetMyGroupMeetingQuery($id))));
    }

    /** @param array<string, mixed> $body */
    private static function private(array $body): JsonResponse
    {
        $response = new JsonResponse($body);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    /** @return array<string, mixed> */
    private static function community(MemberCommunity $c): array
    {
        return [
            'id' => $c->id,
            'kind' => $c->kind,
            'name' => $c->name,
            'description' => $c->description,
            'campus' => $c->campus,
            'parentName' => $c->parentName,
            'joinedAt' => Json::instant($c->joinedAt),
            'role' => $c->role,
            'coverImageUrl' => ImageUrl::public($c->coverImageId),
        ];
    }

    /** @return array<string, mixed> */
    private static function meetingJson(MemberGroupMeeting $m): array
    {
        return [
            'id' => $m->id,
            'communityId' => $m->communityId,
            'communityName' => $m->communityName,
            'startsAt' => Json::instant($m->startsAt),
            'endsAt' => Json::instant($m->endsAt),
            'timeZone' => $m->timeZone,
            'venue' => $m->venue,
            'status' => $m->status,
            'attendance' => $m->attendance,
        ];
    }
}
