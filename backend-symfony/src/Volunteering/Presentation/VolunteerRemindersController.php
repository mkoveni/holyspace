<?php

declare(strict_types=1);

namespace App\Volunteering\Presentation;

use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\Json;
use App\Shared\Presentation\PathUuid;
use App\Volunteering\Application\Command\ApproveVolunteerReminder\ApproveVolunteerReminderCommand;
use App\Volunteering\Application\Query\ListReminderContacts\ListReminderContactsHandler;
use App\Volunteering\Application\Query\ListReminderContacts\ListReminderContactsQuery;
use App\Volunteering\Application\Query\ListVolunteerDeliveries\ListVolunteerDeliveriesHandler;
use App\Volunteering\Application\Query\ListVolunteerDeliveries\ListVolunteerDeliveriesQuery;
use App\Volunteering\Application\Query\ReviewVolunteerReminder\ReviewVolunteerReminderHandler;
use App\Volunteering\Application\Query\ReviewVolunteerReminder\ReviewVolunteerReminderQuery;
use App\Volunteering\Presentation\Request\ReminderApprovalRequest;
use App\Volunteering\Presentation\Request\ReminderDraftRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Java `VolunteerRemindersController`: reviewed, approved shift reminders. Everything needs volunteering.communications.send. */
#[IsGranted('volunteering.communications.send')]
final readonly class VolunteerRemindersController
{
    public function __construct(
        private AuthenticatedAccount $account,
        private CommandBus $commands,
        private ListReminderContactsHandler $contacts,
        private ReviewVolunteerReminderHandler $review,
        private ListVolunteerDeliveriesHandler $deliveries,
    ) {
    }

    #[Route('/api/volunteering/assignments/{id}/reminder-contacts', methods: ['GET'])]
    public function contacts(#[PathUuid] string $id): JsonResponse
    {
        return new JsonResponse(VolunteeringJson::contacts($this->contacts->handle(new ListReminderContactsQuery($id))));
    }

    #[Route('/api/volunteering/assignments/{id}/reminder-review', methods: ['POST'])]
    public function review(#[PathUuid] string $id, #[MapRequestPayload] ReminderDraftRequest $request): JsonResponse
    {
        return new JsonResponse(VolunteeringJson::preview($this->review->handle(new ReviewVolunteerReminderQuery($id, Json::uuid((string) $request->contactId), $request->message))));
    }

    #[Route('/api/volunteering/assignments/{id}/reminders', methods: ['POST'])]
    public function approve(#[PathUuid] string $id, #[MapRequestPayload] ReminderApprovalRequest $request): JsonResponse
    {
        return new JsonResponse($this->commands->dispatch(new ApproveVolunteerReminderCommand(
            $this->account->requireAccount(),
            Json::uuid((string) $request->requestId),
            $id,
            Json::uuid((string) $request->contactId),
            $request->message,
            $request->reviewHash,
            $request->verificationEvidence,
        )));
    }

    #[Route('/api/volunteering/deliveries', methods: ['GET'])]
    public function deliveries(
        #[MapQueryParameter(validationFailedStatusCode: 400)] ?string $status = null,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return new JsonResponse(VolunteeringJson::deliveries($this->deliveries->handle(new ListVolunteerDeliveriesQuery($status, $limit, $offset))));
    }
}
