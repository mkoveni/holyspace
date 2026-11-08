<?php

declare(strict_types=1);

namespace App\Communities\Presentation;

use App\Communications\Application\Contract\DeliverySummary;
use App\Communities\Application\Command\ApproveCommunityNotice\ApproveCommunityNoticeCommand;
use App\Communities\Application\Contract\ReviewedNotice;
use App\Communities\Application\Query\ListCommunityContacts\ListCommunityContactsHandler;
use App\Communities\Application\Query\ListCommunityContacts\ListCommunityContactsQuery;
use App\Communities\Application\Query\ListCommunityDeliveries\ListCommunityDeliveriesHandler;
use App\Communities\Application\Query\ListCommunityDeliveries\ListCommunityDeliveriesQuery;
use App\Communities\Application\Query\ReviewCommunityNotice\ReviewCommunityNoticeHandler;
use App\Communities\Application\Query\ReviewCommunityNotice\ReviewCommunityNoticeQuery;
use App\Communities\Presentation\Request\ApproveCommunityNoticeRequest;
use App\Communities\Presentation\Request\ReviewCommunityNoticeRequest;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\People\Application\Contract\PersonEmail;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\Json;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Reviewed member notices and their delivery outcomes (Java `CommunityMessagingController`). */
#[IsGranted('ministries.communications.send')]
final readonly class CommunityMessagingController
{
    public function __construct(
        private AuthenticatedAccount $account,
        private CommandBus $commands,
        private ListCommunityContactsHandler $contacts,
        private ReviewCommunityNoticeHandler $review,
        private ListCommunityDeliveriesHandler $deliveries,
    ) {
    }

    #[Route('/api/communities/memberships/{membershipId}/contacts', methods: ['GET'])]
    public function contacts(#[PathUuid] string $membershipId): JsonResponse
    {
        return new JsonResponse(array_map(
            static fn (PersonEmail $email): array => ['id' => $email->id, 'value' => $email->value],
            $this->contacts->handle(new ListCommunityContactsQuery($membershipId)),
        ));
    }

    #[Route('/api/communities/memberships/{membershipId}/notice-review', methods: ['POST'])]
    public function review(#[PathUuid] string $membershipId, #[MapRequestPayload] ReviewCommunityNoticeRequest $request): JsonResponse
    {
        return new JsonResponse(self::notice($this->review->handle(new ReviewCommunityNoticeQuery($membershipId, (string) CommunitiesJson::uuid($request->contactId), $request->message))));
    }

    #[Route('/api/communities/memberships/{membershipId}/notices', methods: ['POST'])]
    public function approve(#[PathUuid] string $membershipId, #[MapRequestPayload] ApproveCommunityNoticeRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new ApproveCommunityNoticeCommand(
            $this->account->requireAccount(),
            (string) CommunitiesJson::uuid($request->requestId),
            $membershipId,
            (string) CommunitiesJson::uuid($request->contactId),
            $request->message,
            $request->reviewHash,
            $request->evidence,
        ));

        return new JsonResponse(['id' => $id], 201);
    }

    #[Route('/api/communities/{communityId}/deliveries', methods: ['GET'])]
    public function deliveries(
        #[PathUuid] string $communityId,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return new JsonResponse(array_map(static fn (DeliverySummary $d): array => [
            'id' => $d->id,
            'communicationId' => $d->communicationId,
            'status' => $d->status,
            'scheduledAt' => Json::instant($d->scheduledAt),
            'attempts' => $d->attempts,
            'errorCode' => $d->errorCode,
            'captured' => $d->captured,
            'version' => $d->version,
        ], $this->deliveries->handle(new ListCommunityDeliveriesQuery($communityId, $limit, $offset))));
    }

    /** @return array<string, mixed> */
    private static function notice(ReviewedNotice $n): array
    {
        return ['hash' => $n->hash, 'recipient' => $n->recipient, 'subject' => $n->subject, 'html' => $n->html, 'templateVersion' => $n->templateVersion];
    }
}
