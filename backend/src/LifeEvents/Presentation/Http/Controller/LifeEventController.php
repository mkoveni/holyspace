<?php

declare(strict_types=1);

namespace App\LifeEvents\Presentation\Http\Controller;

use App\LifeEvents\Application\Command\CancelLifeEvent\CancelLifeEventCommand;
use App\LifeEvents\Application\Command\DeleteLifeEvent\DeleteLifeEventCommand;
use App\LifeEvents\Application\Command\RecordLifeEvent\RecordLifeEventCommand;
use App\LifeEvents\Application\Command\RestoreLifeEvent\RestoreLifeEventCommand;
use App\LifeEvents\Application\Command\UpdateLifeEvent\UpdateLifeEventCommand;
use App\LifeEvents\Application\Query\GetLifeEvent\GetLifeEventHandler;
use App\LifeEvents\Application\Query\GetLifeEvent\GetLifeEventQuery;
use App\LifeEvents\Application\Query\GetPersonLifeEvents\GetPersonLifeEventsHandler;
use App\LifeEvents\Application\Query\GetPersonLifeEvents\GetPersonLifeEventsQuery;
use App\LifeEvents\Application\Query\ListLifeEvents\ListLifeEventsHandler;
use App\LifeEvents\Application\Query\ListLifeEvents\ListLifeEventsQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class LifeEventController
{
    public function __construct(private MessageBusInterface $bus, private GetLifeEventHandler $getLifeEvent, private GetPersonLifeEventsHandler $getPersonLifeEvents, private ListLifeEventsHandler $listLifeEvents) {}
    #[Route('/api/life-events', methods: ['POST'])] public function create(Request $request): JsonResponse
    {
        $d = $request->toArray();
        $e = $this->bus->dispatch(new RecordLifeEventCommand((string)$d['type'], (array)$d['participants'], (string)$d['eventDate'], (array)($d['details'] ?? []), $d['notes'] ?? null));
        $handled = $e->last(HandledStamp::class);
        return new JsonResponse(['id' => $handled?->getResult()?->toString()], Response::HTTP_CREATED);
    }
    #[Route('/api/life-events/{id}', methods: ['GET'])] public function get(string $id): JsonResponse
    {
        return new JsonResponse(get_object_vars(($this->getLifeEvent)(new GetLifeEventQuery($id))));
    }
    #[Route('/api/life-events', methods: ['GET'])] public function list(Request $request): JsonResponse
    {
        $type = $request->query->get('type');
        return new JsonResponse(array_map(static fn($v) => get_object_vars($v), ($this->listLifeEvents)(new ListLifeEventsQuery($type === null ? null : (string)$type))));
    }
    #[Route('/api/people/{personId}/life-events', methods: ['GET'])] public function forPerson(string $personId): JsonResponse
    {
        return new JsonResponse(array_map(static fn($v) => get_object_vars($v), ($this->getPersonLifeEvents)(new GetPersonLifeEventsQuery($personId))));
    }
    #[Route('/api/life-events/{id}', methods: ['PUT'])] public function update(string $id, Request $request): JsonResponse
    {
        $d = $request->toArray();
        $this->bus->dispatch(new UpdateLifeEventCommand($id, (string)$d['type'], (array)$d['participants'], (string)$d['eventDate'], (array)($d['details'] ?? []), $d['notes'] ?? null));
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
    #[Route('/api/life-events/{id}/cancel', methods: ['POST'])] public function cancel(string $id): JsonResponse
    {
        $this->bus->dispatch(new CancelLifeEventCommand($id));
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
    #[Route('/api/life-events/{id}/restore', methods: ['POST'])] public function restore(string $id): JsonResponse
    {
        $this->bus->dispatch(new RestoreLifeEventCommand($id));
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
    #[Route('/api/life-events/{id}', methods: ['DELETE'])] public function delete(string $id): JsonResponse
    {
        $this->bus->dispatch(new DeleteLifeEventCommand($id));
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
