<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Presentation\Http\Controller;

use App\ChildrenMinistry\Application\Command\CollectChild\CollectChildCommand;
use App\ChildrenMinistry\Application\Command\CollectChild\CollectChildHandler;
use App\ChildrenMinistry\Application\Command\PrintPickupLabel\PrintPickupLabelCommand;
use App\ChildrenMinistry\Application\Command\PrintPickupLabel\PrintPickupLabelHandler;
use App\ChildrenMinistry\Application\Command\RecordStationArrival\RecordStationArrivalCommand;
use App\ChildrenMinistry\Application\Command\RecordStationArrival\RecordStationArrivalHandler;
use App\ChildrenMinistry\Application\Command\SaveChildSupport\SaveChildSupportCommand;
use App\ChildrenMinistry\Application\Command\SaveChildSupport\SaveChildSupportHandler;
use App\ChildrenMinistry\Application\Query\GetChildSupport\GetChildSupportHandler;
use App\ChildrenMinistry\Application\Query\GetChildSupport\GetChildSupportQuery;
use App\ChildrenMinistry\Application\Query\GetPickupLabel\GetPickupLabelHandler;
use App\ChildrenMinistry\Application\Query\GetPickupLabel\GetPickupLabelQuery;
use App\ChildrenMinistry\Application\Query\GetStationRoster\GetStationRosterHandler;
use App\ChildrenMinistry\Application\Query\GetStationRoster\GetStationRosterQuery;
use App\ChildrenMinistry\Application\Query\ListStationSessions\ListStationSessionsHandler;
use App\ChildrenMinistry\Application\Query\ListStationSessions\ListStationSessionsQuery;
use App\ChildrenMinistry\Presentation\Http\DTO\LabelPrintPayload;
use App\ChildrenMinistry\Presentation\Http\DTO\StationArrivalPayload;
use App\ChildrenMinistry\Presentation\Http\DTO\StationCollectionPayload;
use App\ChildrenMinistry\Presentation\Http\DTO\SupportNotePayload;
use App\Shared\Presentation\Http\WorkflowController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/children-station', format: 'json')]
#[IsGranted('children.station')]
final class CheckInStationController extends WorkflowController
{
    public function __construct(
        private readonly ListStationSessionsHandler $listStationSessionsHandler,
        private readonly GetStationRosterHandler $getStationRosterHandler,
        private readonly RecordStationArrivalHandler $recordStationArrivalHandler,
        private readonly GetPickupLabelHandler $getPickupLabelHandler,
        private readonly PrintPickupLabelHandler $printPickupLabelHandler,
        private readonly CollectChildHandler $collectChildHandler,
        private readonly GetChildSupportHandler $getChildSupportHandler,
        private readonly SaveChildSupportHandler $saveChildSupportHandler,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function sessions(): JsonResponse
    {
        return $this->result(fn () => ($this->listStationSessionsHandler)(new ListStationSessionsQuery()));
    }

    #[Route('/sessions/{id}', methods: ['GET'])]
    public function roster(string $id): JsonResponse
    {
        return $this->result(fn () => ($this->getStationRosterHandler)(new GetStationRosterQuery($id)));
    }

    #[Route('/sessions/{id}/arrivals', methods: ['POST'])]
    public function arrive(
        string $id,
        #[MapRequestPayload]
        StationArrivalPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->recordStationArrivalHandler)(new RecordStationArrivalCommand($id, $p->childId)), 201);
    }

    #[Route('/checkins/{id}/label', methods: ['GET'])]
    public function label(string $id): JsonResponse
    {
        return $this->result(fn () => ($this->getPickupLabelHandler)(new GetPickupLabelQuery($id)));
    }

    #[Route('/checkins/{id}/label', methods: ['POST'])]
    public function printLabel(
        string $id,
        #[MapRequestPayload]
        LabelPrintPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->printPickupLabelHandler)(new PrintPickupLabelCommand($id, $p->expectedVersion, $p->replacementReason)));
    }

    #[Route('/checkins/{id}/collect', methods: ['POST'])]
    public function collect(
        string $id,
        #[MapRequestPayload]
        StationCollectionPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->collectChildHandler)(new CollectChildCommand($id, get_object_vars($p))));
    }

    #[Route('/sessions/{id}/children/{childId}/support', methods: ['GET'])]
    public function support(string $id, string $childId): JsonResponse
    {
        return $this->result(fn () => ($this->getChildSupportHandler)(new GetChildSupportQuery($id, $childId)));
    }

    #[Route('/sessions/{id}/children/{childId}/support', methods: ['PUT'])]
    public function saveSupport(
        string $id,
        string $childId,
        #[MapRequestPayload]
        SupportNotePayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->saveChildSupportHandler)(new SaveChildSupportCommand($id, $childId, $p->note, $p->expectedVersion)));
    }

    protected function result(callable $operation, int $status = 200): JsonResponse
    {
        $response = parent::result($operation, $status);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->setVary('Authorization', false);

        return $response;
    }
}
