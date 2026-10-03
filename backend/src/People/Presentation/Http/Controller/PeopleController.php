<?php

declare(strict_types=1);

namespace App\People\Presentation\Http\Controller;

use App\People\Application\Command\ChangeMembershipStatus\ChangeMembershipStatusCommand;
use App\People\Application\Command\RegisterPerson\RegisterPersonCommand;
use App\People\Application\Command\UpdatePerson\UpdatePersonCommand;
use App\People\Application\Query\GetPerson\GetPersonHandler;
use App\People\Application\Query\GetPerson\GetPersonQuery;
use App\People\Application\Query\ListPeople\ListPeopleHandler;
use App\People\Application\Query\ListPeople\ListPeopleQuery;
use App\People\Presentation\Http\Service\CommandDispatcher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class PeopleController extends AbstractController
{
    public function __construct(
        private readonly CommandDispatcher $commands,
        private readonly GetPersonHandler $getPerson,
        private readonly ListPeopleHandler $listPeople,
    ) {
    }

    #[Route('/api/people', methods: ['POST'], name: 'app_people_presentation_http_people_createperson')]
    public function createPerson(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $id = $this->commands->dispatch(new RegisterPersonCommand(
            (string) ($data['firstName'] ?? ''),
            (string) ($data['lastName'] ?? ''),
            isset($data['dateOfBirth']) ? (string) $data['dateOfBirth'] : null,
            (string) ($data['gender'] ?? 'unspecified'),
            isset($data['email']) ? (string) $data['email'] : null,
            isset($data['phone']) ? (string) $data['phone'] : null,
        ));
        return new JsonResponse(['id' => $id?->toString()], 201);
    }

    #[Route('/api/people', methods: ['GET'], name: 'app_people_presentation_http_people_listpeople')]
    public function listPeople(): JsonResponse
    {
        return new JsonResponse(array_map(static fn ($view) => get_object_vars($view), ($this->listPeople)(new ListPeopleQuery())));
    }

    #[Route('/api/people/{id}', methods: ['GET'], name: 'app_people_presentation_http_people_getperson')]
    public function getPerson(string $id): JsonResponse
    {
        return new JsonResponse(get_object_vars(($this->getPerson)(new GetPersonQuery($id))));
    }

    #[Route('/api/people/{id}', methods: ['PUT'], name: 'app_people_presentation_http_people_updateperson')]
    public function updatePerson(string $id, Request $request): JsonResponse
    {
        $data = $request->toArray();
        $this->commands->dispatch(new UpdatePersonCommand(
            $id,
            (string) ($data['firstName'] ?? ''),
            (string) ($data['lastName'] ?? ''),
            isset($data['dateOfBirth']) ? (string) $data['dateOfBirth'] : null,
            (string) ($data['gender'] ?? 'unspecified'),
            isset($data['email']) ? (string) $data['email'] : null,
            isset($data['phone']) ? (string) $data['phone'] : null,
        ));
        return new JsonResponse(null, 204);
    }

    #[Route('/api/people/{id}/status', methods: ['POST'], name: 'app_people_presentation_http_people_status')]
    public function status(string $id, Request $request): JsonResponse
    {
        $this->commands->dispatch(new ChangeMembershipStatusCommand(
            $id,
            (string) ($request->toArray()['status'] ?? ''),
        ));
        return new JsonResponse(null, 204);
    }
}
