<?php

declare(strict_types=1);

namespace App\Events\Presentation;

use App\Events\Application\Command\ApproveEventNotification\ApproveEventNotificationCommand;
use App\Events\Application\Query\ListEventDeliveries\ListEventDeliveriesHandler;
use App\Events\Application\Query\ListEventDeliveries\ListEventDeliveriesQuery;
use App\Events\Application\Query\ListEventMessageContacts\ListEventMessageContactsHandler;
use App\Events\Application\Query\ListEventMessageContacts\ListEventMessageContactsQuery;
use App\Events\Application\Query\ReviewEventNotification\ReviewEventNotificationHandler;
use App\Events\Application\Query\ReviewEventNotification\ReviewEventNotificationQuery;
use App\Events\Presentation\Request\ApproveEventNotificationRequest;
use App\Events\Presentation\Request\ReviewEventNotificationRequest;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\People\Application\Contract\PersonEmail;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Reviewed email notices to registered people. Everything here needs events.communications.send. */
#[Route('/api/events')]
#[IsGranted('events.communications.send')]
final readonly class EventNotificationsController
{
    public function __construct(
        private CommandBus $commands,
        private AuthenticatedAccount $account,
        private ListEventMessageContactsHandler $contacts,
        private ReviewEventNotificationHandler $review,
        private ListEventDeliveriesHandler $deliveries,
    ) {
    }

    #[Route('/registrations/{id}/notifications', methods: ['POST'])]
    public function approve(#[PathUuid] string $id, #[MapRequestPayload] ApproveEventNotificationRequest $request): JsonResponse
    {
        $delivery = $this->commands->dispatch(new ApproveEventNotificationCommand(
            $this->account->requireAccount(),
            strtolower((string) $request->requestId),
            $id,
            strtolower((string) $request->contactId),
            $request->purpose,
            $request->message,
            $request->reviewHash,
            $request->verificationEvidence,
        ));

        return $this->noStore(new JsonResponse(['id' => $delivery], 201));
    }

    #[Route('/registrations/{id}/message-contacts', methods: ['GET'])]
    public function contacts(#[PathUuid] string $id): JsonResponse
    {
        return $this->noStore(new JsonResponse(array_map(
            static fn (PersonEmail $email): array => ['id' => $email->id, 'value' => $email->value],
            $this->contacts->handle(new ListEventMessageContactsQuery($id)),
        )));
    }

    #[Route('/registrations/{id}/notification-review', methods: ['POST'])]
    public function review(#[PathUuid] string $id, #[MapRequestPayload] ReviewEventNotificationRequest $request): JsonResponse
    {
        return $this->noStore(new JsonResponse(EventsJson::notice($this->review->handle(new ReviewEventNotificationQuery($id, strtolower((string) $request->contactId), $request->purpose, $request->message)))));
    }

    #[Route('/deliveries', methods: ['GET'])]
    public function deliveries(
        #[MapQueryParameter] ?string $status = null,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return $this->noStore(new JsonResponse(EventsJson::deliveries($this->deliveries->handle(new ListEventDeliveriesQuery($status, $limit, $offset)))));
    }

    /** Recipient addresses and rendered content are personal data and must not be cached. */
    private function noStore(JsonResponse $response): JsonResponse
    {
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
