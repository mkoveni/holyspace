<?php

declare(strict_types=1);

namespace App\Events\Presentation\Http\Controller;

use App\Events\Application\Command\CreateEvent;
use App\Events\Application\Command\UpdateEvent;
use App\Events\Application\Command\UpdateOccurrence;
use App\Events\Application\Query\GetEvent\GetEventHandler;
use App\Events\Application\Query\GetEvent\GetEventQuery;
use App\Events\Application\Query\ListEvents\ListEventsHandler;
use App\Events\Application\Query\ListEvents\ListEventsQuery;
use App\Events\Presentation\Http\DTO\CreateEventPayload;
use App\Events\Presentation\Http\DTO\EventDetailsPayload;
use App\Events\Presentation\Http\DTO\OccurrencePayload;
use App\Shared\Presentation\Http\DTO\CreatedResponse;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[Route('/api/events', format: 'json')]
final class EventsController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly ListEventsHandler $listEventsHandler,
        private readonly GetEventHandler $getEventHandler,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json(($this->listEventsHandler)(new ListEventsQuery()));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        return $this->execute(fn () => $this->json(($this->getEventHandler)(new GetEventQuery($id))));
    }

    #[Route('', methods: ['POST'])]
    public function create(
        #[MapRequestPayload]
        CreateEventPayload $p,
    ): JsonResponse {
        return $this->execute(function () use ($p) {
            $id = $this->bus->dispatch(new CreateEvent($p->title, $p->type, $p->description, $p->timezone, $p->startsAt, $p->endsAt, $p->location, $p->recurrence, $p->occurrenceCount))->last(HandledStamp::class)?->getResult();

            return $this->json(new CreatedResponse($id->toString()), 201);
        });
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(
        string $id,
        #[MapRequestPayload]
        EventDetailsPayload $p,
    ): JsonResponse {
        return $this->execute(function () use ($id, $p) {
            $this->bus->dispatch(new UpdateEvent($id, $p->title, $p->type, $p->description));

            return new JsonResponse(null, 204);
        });
    }

    #[Route('/{id}/occurrences/{occurrenceId}', methods: ['PUT'])]
    public function occurrence(
        string $id,
        string $occurrenceId,
        #[MapRequestPayload]
        OccurrencePayload $p,
    ): JsonResponse {
        return $this->execute(function () use ($id, $occurrenceId, $p) {
            $this->bus->dispatch(new UpdateOccurrence($id, $occurrenceId, $p->startsAt, $p->endsAt, $p->location, $p->status));

            return new JsonResponse(null, 204);
        });
    }

    private function execute(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (Throwable $error) {
            while ($error instanceof HandlerFailedException && null !== $error->getPrevious()) {
                $error = $error->getPrevious();
            }
            if ($error instanceof OutOfBoundsException) {
                return $this->json(['message' => $error->getMessage()], 404);
            }
            if ($error instanceof InvalidArgumentException) {
                return $this->json(['message' => $error->getMessage()], 422);
            }
            throw $error;
        }
    }
}
