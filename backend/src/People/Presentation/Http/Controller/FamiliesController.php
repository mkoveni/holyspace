<?php

declare(strict_types=1);

namespace App\People\Presentation\Http\Controller;

use App\People\Application\Command\AddFamilyAddress\AddFamilyAddressCommand;
use App\People\Application\Command\AddFamilyMember\AddFamilyMemberCommand;
use App\People\Application\Command\CreateFamily\CreateFamilyCommand;
use App\People\Application\Query\GetFamily\GetFamilyHandler;
use App\People\Application\Query\GetFamily\GetFamilyQuery;
use App\People\Application\Query\ListFamilies\ListFamiliesHandler;
use App\People\Application\Query\ListFamilies\ListFamiliesQuery;
use App\People\Application\Query\ListFamilyPeople\ListFamilyPeopleHandler;
use App\People\Application\Query\ListFamilyPeople\ListFamilyPeopleQuery;
use App\People\Application\Query\ListPersonFamilies\ListPersonFamiliesHandler;
use App\People\Application\Query\ListPersonFamilies\ListPersonFamiliesQuery;
use App\People\Presentation\Http\Service\CommandDispatcher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class FamiliesController extends AbstractController
{
    public function __construct(
        private readonly CommandDispatcher $commands,
        private readonly GetFamilyHandler $getFamily,
        private readonly ListFamiliesHandler $listFamilies,
        private readonly ListPersonFamiliesHandler $personFamilies,
        private readonly ListFamilyPeopleHandler $familyPeople,
    ) {}

    #[Route('/api/families', methods: ['POST'], name: 'app_people_presentation_http_people_createfamily')]
    public function createFamily(Request $request): JsonResponse
    {
        $id = $this->commands->dispatch(new CreateFamilyCommand(
            (string) ($request->toArray()['name'] ?? ''),
        ));
        return new JsonResponse(['id' => $id?->toString()], 201);
    }

    #[Route('/api/families', methods: ['GET'], name: 'app_people_presentation_http_people_listfamilies')]
    public function listFamilies(): JsonResponse
    {
        return new JsonResponse(array_map(static fn($view) => $view, ($this->listFamilies)(new ListFamiliesQuery())));
    }

    #[Route('/api/families/{id}', methods: ['GET'], name: 'app_people_presentation_http_people_getfamily')]
    public function getFamily(string $id): JsonResponse
    {
        return new JsonResponse(($this->getFamily)(new GetFamilyQuery($id)));
    }

    #[Route('/api/families/{familyId}/members', methods: ['POST'], name: 'app_people_presentation_http_people_addmember')]
    public function addMember(string $familyId, Request $request): JsonResponse
    {
        $data = $request->toArray();
        $this->commands->dispatch(new AddFamilyMemberCommand(
            $familyId,
            (string) ($data['personId'] ?? ''),
            (string) ($data['role'] ?? 'other'),
        ));
        return new JsonResponse(null, 204);
    }

    #[Route('/api/people/{personId}/families', methods: ['GET'], name: 'app_people_presentation_http_people_personfamilies')]
    public function personFamilies(string $personId): JsonResponse
    {
        return new JsonResponse(($this->personFamilies)(new ListPersonFamiliesQuery($personId)));
    }

    #[Route('/api/families/{familyId}/people', methods: ['GET'], name: 'app_people_presentation_http_people_familypeople')]
    public function familyPeople(string $familyId): JsonResponse
    {
        return new JsonResponse(($this->familyPeople)(new ListFamilyPeopleQuery($familyId)));
    }

    #[Route('/api/families/{familyId}/addresses', methods: ['POST'], name: 'app_people_presentation_http_people_addfamilyaddress')]
    public function addFamilyAddress(string $familyId, Request $request): JsonResponse
    {
        $data = $request->toArray();
        $id = $this->commands->dispatch(new AddFamilyAddressCommand(
            $familyId,
            (string) ($data['type'] ?? 'residential'),
            (string) ($data['line1'] ?? ''),
            isset($data['line2']) ? (string) $data['line2'] : null,
            (string) ($data['city'] ?? ''),
            isset($data['province']) ? (string) $data['province'] : null,
            isset($data['postalCode']) ? (string) $data['postalCode'] : null,
            (string) ($data['country'] ?? 'South Africa'),
        ));
        return new JsonResponse(['id' => $id?->toString()], 201);
    }
}
