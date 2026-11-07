<?php

declare(strict_types=1);

namespace App\Events\Presentation;

use App\Events\Application\Command\CancelEventRegistration\CancelEventRegistrationCommand;
use App\Events\Application\Command\ConfigureEventRegistration\ConfigureEventRegistrationCommand;
use App\Events\Application\Command\RegisterEventPerson\RegisterEventPersonCommand;
use App\Events\Application\Contract\RegistrationHistoryEntry;
use App\Events\Application\Query\GetEventRegistrationHistory\GetEventRegistrationHistoryHandler;
use App\Events\Application\Query\GetEventRegistrationHistory\GetEventRegistrationHistoryQuery;
use App\Events\Application\Query\ListEventRegistrations\ListEventRegistrationsHandler;
use App\Events\Application\Query\ListEventRegistrations\ListEventRegistrationsQuery;
use App\Events\Presentation\Request\CancelEventRegistrationRequest;
use App\Events\Presentation\Request\ConfigureEventRegistrationRequest;
use App\Events\Presentation\Request\RegisterEventPersonRequest;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Registration policy, bookings and the waitlist. Reads need events.registrations.read, writes .manage. */
#[Route('/api/events')]
final readonly class EventRegistrationsController
{
    public function __construct(
        private CommandBus $commands,
        private AuthenticatedAccount $account,
        private ListEventRegistrationsHandler $list,
        private GetEventRegistrationHistoryHandler $history,
    ) {
    }

    #[IsGranted('events.registrations.manage')]
    #[Route('/occurrences/{id}/registration', methods: ['PUT'])]
    public function configure(#[PathUuid] string $id, #[MapRequestPayload] ConfigureEventRegistrationRequest $request): Response
    {
        $this->commands->dispatch(new ConfigureEventRegistrationCommand(
            $this->account->requireAccount(),
            $id,
            $request->expectedVersion,
            $request->open,
            $request->capacity,
            $request->waitlistEnabled,
            $request->reason,
        ));

        return new Response(status: 204);
    }

    #[IsGranted('events.registrations.manage')]
    #[Route('/occurrences/{id}/registrations', methods: ['POST'])]
    public function register(#[PathUuid] string $id, #[MapRequestPayload] RegisterEventPersonRequest $request): JsonResponse
    {
        $registration = $this->commands->dispatch(new RegisterEventPersonCommand(
            $this->account->requireAccount(),
            strtolower((string) $request->requestId),
            $id,
            strtolower((string) $request->personId),
            $request->expectedVersion,
        ));

        return new JsonResponse(['id' => $registration], 201);
    }

    #[IsGranted('events.registrations.manage')]
    #[Route('/registrations/{id}/cancel', methods: ['POST'])]
    public function cancel(#[PathUuid] string $id, #[MapRequestPayload] CancelEventRegistrationRequest $request): Response
    {
        $this->commands->dispatch(new CancelEventRegistrationCommand($this->account->requireAccount(), $id, $request->expectedVersion, $request->reason));

        return new Response(status: 204);
    }

    #[IsGranted('events.registrations.read')]
    #[Route('/occurrences/{id}/registrations', methods: ['GET'])]
    public function list(
        #[PathUuid] string $id,
        #[MapQueryParameter] ?string $status = null,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return new JsonResponse(EventsJson::registrationPage($this->list->handle(new ListEventRegistrationsQuery($id, $status, $limit, $offset))));
    }

    #[IsGranted('events.registrations.read')]
    #[Route('/registrations/{id}/history', methods: ['GET'])]
    public function history(
        #[PathUuid] string $id,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return new JsonResponse(array_map(
            static fn (RegistrationHistoryEntry $entry): array => EventsJson::registrationEntry($entry),
            $this->history->handle(new GetEventRegistrationHistoryQuery($id, $limit, $offset)),
        ));
    }
}
