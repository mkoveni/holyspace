<?php

declare(strict_types=1);

namespace App\Events\Presentation;

use App\Events\Application\Command\CreateEventVenue\CreateEventVenueCommand;
use App\Events\Application\Command\UpdateEventVenue\UpdateEventVenueCommand;
use App\Events\Application\Contract\VenueView;
use App\Events\Application\Query\ListEventVenues\ListEventVenuesHandler;
use App\Events\Application\Query\ListEventVenues\ListEventVenuesQuery;
use App\Events\Presentation\Request\CreateEventVenueRequest;
use App\Events\Presentation\Request\UpdateEventVenueRequest;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Event venues. Reads need events.read, writes events.manage. */
#[Route('/api/events/venues')]
final readonly class EventVenuesController
{
    public function __construct(
        private CommandBus $commands,
        private AuthenticatedAccount $account,
        private ListEventVenuesHandler $list,
    ) {
    }

    #[IsGranted('events.manage')]
    #[Route('', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateEventVenueRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new CreateEventVenueCommand(
            $this->account->requireAccount(),
            $request->name,
            $request->address,
            $request->exclusive,
        ));

        return new JsonResponse(['id' => $id], 201);
    }

    #[IsGranted('events.manage')]
    #[Route('/{id}', methods: ['PUT'])]
    public function update(#[PathUuid] string $id, #[MapRequestPayload] UpdateEventVenueRequest $request): Response
    {
        $this->commands->dispatch(new UpdateEventVenueCommand(
            $this->account->requireAccount(),
            $id,
            $request->expectedVersion,
            $request->name,
            $request->address,
            $request->active,
        ));

        return new Response(status: 204);
    }

    #[IsGranted('events.read')]
    #[Route('', methods: ['GET'])]
    public function list(
        #[MapQueryParameter] string $search = '',
        #[MapQueryParameter] bool $includeArchived = false,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $page = $this->list->handle(new ListEventVenuesQuery($search, $includeArchived, $limit, $offset));

        return new JsonResponse([
            'venues' => array_map(static fn (VenueView $venue): array => EventsJson::venue($venue), $page->venues),
            'total' => $page->total,
        ]);
    }
}
