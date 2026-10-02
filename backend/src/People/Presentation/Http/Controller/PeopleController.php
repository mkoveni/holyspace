<?php

declare(strict_types=1);

namespace App\People\Presentation\Http\Controller;

use App\People\Application\Command\AddFamilyMember\AddFamilyMemberCommand;
use App\People\Application\Command\ChangeMembershipStatus\ChangeMembershipStatusCommand;
use App\People\Application\Command\CreateFamily\CreateFamilyCommand;
use App\People\Application\Command\CreateRelationship\CreateRelationshipCommand;
use App\People\Application\Command\EndRelationship\EndRelationshipCommand;
use App\People\Application\Command\RegisterPerson\RegisterPersonCommand;
use App\People\Application\Command\UpdatePerson\UpdatePersonCommand;
use App\People\Application\Query\GetFamily\GetFamilyHandler;
use App\People\Application\Query\GetFamily\GetFamilyQuery;
use App\People\Application\Query\GetPerson\GetPersonHandler;
use App\People\Application\Query\GetPerson\GetPersonQuery;
use App\People\Application\Query\GetPersonRelationships\GetPersonRelationshipsHandler;
use App\People\Application\Query\GetPersonRelationships\GetPersonRelationshipsQuery;
use App\People\Application\Query\ListFamilies\ListFamiliesHandler;
use App\People\Application\Query\ListFamilies\ListFamiliesQuery;
use App\People\Application\Query\ListPeople\ListPeopleHandler;
use App\People\Application\Query\ListPeople\ListPeopleQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

final readonly class PeopleController
{
    public function __construct(
        private MessageBusInterface $bus,
        private GetPersonHandler $getPerson,
        private ListPeopleHandler $listPeople,
        private GetFamilyHandler $getFamily,
        private ListFamiliesHandler $listFamilies,
        private GetPersonRelationshipsHandler $getRelationships
    ) {}

    private function result(object $m): mixed
    {
        $e = $this->bus->dispatch($m);
        return $e->last(HandledStamp::class)?->getResult();
    }

    #[Route('/api/people', methods: ['POST'])]
    public function createPerson(Request $r): JsonResponse
    {
        $d = $r->toArray();
        $id = $this->result(new RegisterPersonCommand((string)($d['firstName'] ?? ''), (string)($d['lastName'] ?? ''), isset($d['dateOfBirth']) ? (string)$d['dateOfBirth'] : null, (string)($d['gender'] ?? 'unspecified'), isset($d['email']) ? (string)$d['email'] : null, isset($d['phone']) ? (string)$d['phone'] : null));
        return new JsonResponse(['id' => $id?->toString()], 201);
    }

    #[Route('/api/people', methods: ['GET'])]
    public function listPeople(): JsonResponse
    {
        return new JsonResponse(array_map(fn($v) => get_object_vars($v), ($this->listPeople)(new ListPeopleQuery())));
    }

    #[Route('/api/people/{id}', methods: ['GET'])]
    public function getPerson(string $id): JsonResponse
    {
        return new JsonResponse(get_object_vars(($this->getPerson)(new GetPersonQuery($id))));
    }

    #[Route('/api/people/{id}', methods: ['PUT'])]
    public function updatePerson(string $id, Request $r): JsonResponse
    {
        $d = $r->toArray();
        $this->bus->dispatch(new UpdatePersonCommand($id, (string)($d['firstName'] ?? ''), (string)($d['lastName'] ?? ''), isset($d['dateOfBirth']) ? (string)$d['dateOfBirth'] : null, (string)($d['gender'] ?? 'unspecified'), isset($d['email']) ? (string)$d['email'] : null, isset($d['phone']) ? (string)$d['phone'] : null));
        return new JsonResponse(null, 204);
    }

    #[Route('/api/people/{id}/status', methods: ['POST'])]
    public function status(string $id, Request $r): JsonResponse
    {
        $this->bus->dispatch(new ChangeMembershipStatusCommand($id, (string)($r->toArray()['status'] ?? '')));
        return new JsonResponse(null, 204);
    }

    #[Route('/api/families', methods: ['POST'])]
    public function createFamily(Request $r): JsonResponse
    {
        $id = $this->result(new CreateFamilyCommand((string)($r->toArray()['name'] ?? '')));
        return new JsonResponse(['id' => $id?->toString()], 201);
    }

    #[Route('/api/families', methods: ['GET'])]
    public function listFamilies(): JsonResponse
    {
        return new JsonResponse(array_map(fn($v) => get_object_vars($v), ($this->listFamilies)(new ListFamiliesQuery())));
    }

    #[Route('/api/families/{id}', methods: ['GET'])]
    public function getFamily(string $id): JsonResponse
    {
        return new JsonResponse(get_object_vars(($this->getFamily)(new GetFamilyQuery($id))));
    }

    #[Route('/api/families/{familyId}/members', methods: ['POST'])]
    public function addMember(string $familyId, Request $r): JsonResponse
    {
        $d = $r->toArray();
        $this->bus->dispatch(new AddFamilyMemberCommand($familyId, (string)($d['personId'] ?? ''), (string)($d['role'] ?? 'other')));
        return new JsonResponse(null, 204);
    }

    #[Route('/api/people/{personId}/relationships', methods: ['GET'])]
    public function relationships(string $personId): JsonResponse
    {
        return new JsonResponse(array_map(fn($v) => get_object_vars($v), ($this->getRelationships)(new GetPersonRelationshipsQuery($personId))));
    }

    #[Route('/api/people/{personId}/relationships', methods: ['POST'])]
    public function createRelationship(string $personId, Request $r): JsonResponse
    {
        $d = $r->toArray();
        $id = $this->result(new CreateRelationshipCommand($personId, (string)($d['relatedPersonId'] ?? ''), (string)($d['type'] ?? '')));
        return new JsonResponse(['id' => $id?->toString()], 201);
    }

    #[Route('/api/relationships/{id}/end', methods: ['POST'])] public function endRelationship(string $id, Request $r): JsonResponse
    {
        $this->bus->dispatch(new EndRelationshipCommand($id, (string)(($r->toArray())['endedAt'] ?? date('Y-m-d H:i:s'))));
        return new JsonResponse(null, 204);
    }
}
