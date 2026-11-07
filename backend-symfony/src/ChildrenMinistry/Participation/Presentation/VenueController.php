<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Presentation;

use App\ChildrenMinistry\Participation\Application\Command\CreateLocation\CreateLocationCommand;
use App\ChildrenMinistry\Participation\Application\Command\CreateRoom\CreateRoomCommand;
use App\ChildrenMinistry\Participation\Application\LocationView;
use App\ChildrenMinistry\Participation\Application\Query\ListLocations\ListLocationsHandler;
use App\ChildrenMinistry\Participation\Application\Query\ListLocations\ListLocationsQuery;
use App\ChildrenMinistry\Participation\Application\Query\ListRooms\ListRoomsHandler;
use App\ChildrenMinistry\Participation\Application\Query\ListRooms\ListRoomsQuery;
use App\ChildrenMinistry\Participation\Application\RoomView;
use App\ChildrenMinistry\Participation\Presentation\Request\CreateLocationRequest;
use App\ChildrenMinistry\Participation\Presentation\Request\CreateRoomRequest;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\Json;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Locations (campuses) and the rooms inside them. Reads need children.read, writes children.manage. */
#[Route('/api/children')]
final readonly class VenueController
{
    public function __construct(
        private AuthenticatedAccount $account,
        private CommandBus $commands,
        private ListLocationsHandler $locations,
        private ListRoomsHandler $rooms,
    ) {
    }

    #[IsGranted('children.manage')]
    #[Route('/locations', methods: ['POST'])]
    public function createLocation(#[MapRequestPayload] CreateLocationRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new CreateLocationCommand($this->account->requireAccount(), $request->name, $request->timeZone));

        return new JsonResponse(['id' => $id], 201);
    }

    #[IsGranted('children.read')]
    #[Route('/locations', methods: ['GET'])]
    public function listLocations(
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 50,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $locations = $this->locations->handle(new ListLocationsQuery($limit, $offset));

        return new JsonResponse(array_map(static fn (LocationView $location): array => ParticipationJson::location($location), $locations));
    }

    #[IsGranted('children.manage')]
    #[Route('/rooms', methods: ['POST'])]
    public function createRoom(#[MapRequestPayload] CreateRoomRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new CreateRoomCommand(
            $this->account->requireAccount(),
            Json::uuid($request->locationId ?? throw new BadRequestException('Location is required.')),
            $request->name,
            $request->capacity,
        ));

        return new JsonResponse(['id' => $id], 201);
    }

    #[IsGranted('children.read')]
    #[Route('/rooms', methods: ['GET'])]
    public function listRooms(#[MapQueryParameter] ?string $locationId = null): JsonResponse
    {
        $rooms = $this->rooms->handle(new ListRoomsQuery(null === $locationId ? null : Json::uuid($locationId)));

        return new JsonResponse(array_map(static fn (RoomView $room): array => ParticipationJson::room($room), $rooms));
    }
}
