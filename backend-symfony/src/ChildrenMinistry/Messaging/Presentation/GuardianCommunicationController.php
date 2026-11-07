<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Presentation;

use App\ChildrenMinistry\Messaging\Application\Command\CreateGuardianCommunication\CreateGuardianCommunicationCommand;
use App\ChildrenMinistry\Messaging\Application\Command\QueueGuardianEmail\QueueGuardianEmailCommand;
use App\ChildrenMinistry\Messaging\Application\Command\RecordGuardianContact\RecordGuardianContactCommand;
use App\ChildrenMinistry\Messaging\Application\Command\VerifyGuardianEmail\VerifyGuardianEmailCommand;
use App\ChildrenMinistry\Messaging\Application\Query\GetEmailReview\GetEmailReviewHandler;
use App\ChildrenMinistry\Messaging\Application\Query\GetEmailReview\GetEmailReviewQuery;
use App\ChildrenMinistry\Messaging\Application\Query\GetGuardianCommunication\GetGuardianCommunicationHandler;
use App\ChildrenMinistry\Messaging\Application\Query\GetGuardianCommunication\GetGuardianCommunicationQuery;
use App\ChildrenMinistry\Messaging\Application\Query\ListGuardianCommunications\ListGuardianCommunicationsHandler;
use App\ChildrenMinistry\Messaging\Application\Query\ListGuardianCommunications\ListGuardianCommunicationsQuery;
use App\ChildrenMinistry\Messaging\Presentation\Request\PrepareCommunicationRequest;
use App\ChildrenMinistry\Messaging\Presentation\Request\QueueEmailRequest;
use App\ChildrenMinistry\Messaging\Presentation\Request\RecordContactRequest;
use App\ChildrenMinistry\Messaging\Presentation\Request\VerifyEmailRequest;
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

/**
 * Messages to guardians. Everything needs children.safeguarding; approving an email also needs
 * children.communications.send. Preparing a draft, verifying an address and recording contact send nothing.
 */
#[Route('/api/children/guardian-communications')]
#[IsGranted('children.safeguarding')]
final readonly class GuardianCommunicationController
{
    public function __construct(
        private AuthenticatedAccount $account,
        private CommandBus $commands,
        private ListGuardianCommunicationsHandler $list,
        private GetGuardianCommunicationHandler $get,
        private GetEmailReviewHandler $review,
    ) {
    }

    #[Route('', methods: ['POST'])]
    public function prepare(#[MapRequestPayload] PrepareCommunicationRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new CreateGuardianCommunicationCommand(
            $this->account->requireAccount(),
            Json::uuid((string) $request->requestId),
            Json::uuid((string) $request->childId),
            Json::uuid((string) $request->authorityId),
            Json::uuid((string) $request->programmeId),
            null === $request->sessionId ? null : Json::uuid($request->sessionId),
            $request->type,
            CalendarDate::parse((string) $request->dueOn),
        ));

        return new JsonResponse(['id' => $id], 201);
    }

    #[Route('', methods: ['GET'])]
    public function list(
        #[MapQueryParameter] ?string $childId = null,
        #[MapQueryParameter] string $status = 'DRAFT',
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return new JsonResponse(MessagingJson::communications($this->list->handle(new ListGuardianCommunicationsQuery(null === $childId ? null : Json::uuid($childId), $status, $limit, $offset))));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(#[PathUuid] string $id): JsonResponse
    {
        return new JsonResponse(MessagingJson::communication($this->get->handle(new GetGuardianCommunicationQuery($id))));
    }

    #[Route('/{id}/contact', methods: ['POST'])]
    public function contact(#[PathUuid] string $id, #[MapRequestPayload] RecordContactRequest $request): Response
    {
        $this->commands->dispatch(new RecordGuardianContactCommand($this->account->requireAccount(), $id, $request->expectedVersion, $request->status, $request->reference));

        return new Response(status: 204);
    }

    #[Route('/{id}/email-review', methods: ['GET'])]
    public function review(#[PathUuid] string $id): JsonResponse
    {
        $response = new JsonResponse(MessagingJson::review($this->review->handle(new GetEmailReviewQuery($id))));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[Route('/{id}/verify-email', methods: ['POST'])]
    public function verify(#[PathUuid] string $id, #[MapRequestPayload] VerifyEmailRequest $request): JsonResponse
    {
        $verification = $this->commands->dispatch(new VerifyGuardianEmailCommand(
            $this->account->requireAccount(),
            $id,
            Json::uuid((string) $request->contactId),
            $request->evidence,
            Json::parseInstant((string) $request->validUntil),
        ));

        return new JsonResponse(['id' => $verification], 201);
    }

    #[IsGranted('children.communications.send')]
    #[Route('/{id}/queue-email', methods: ['POST'])]
    public function queue(#[PathUuid] string $id, #[MapRequestPayload] QueueEmailRequest $request): JsonResponse
    {
        $delivery = $this->commands->dispatch(new QueueGuardianEmailCommand(
            $this->account->requireAccount(),
            $id,
            $request->expectedVersion,
            Json::uuid((string) $request->verificationId),
            (string) $request->reviewHash,
            null === $request->scheduledAt ? null : Json::parseInstant($request->scheduledAt),
        ));

        return new JsonResponse(['id' => $delivery], 201);
    }
}
