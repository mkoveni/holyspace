<?php

declare(strict_types=1);

namespace App\ServicePlanning\Presentation\Http\Controller;

use App\ServicePlanning\Application\Command\PublishServicePlan\PublishServicePlanCommand;
use App\ServicePlanning\Application\Command\PublishServicePlan\PublishServicePlanHandler;
use App\ServicePlanning\Application\Command\SaveServicePlan\SaveServicePlanCommand;
use App\ServicePlanning\Application\Command\SaveServicePlan\SaveServicePlanHandler;
use App\ServicePlanning\Application\Query\GetOwnPublishedPlans\GetOwnPublishedPlansHandler;
use App\ServicePlanning\Application\Query\GetOwnPublishedPlans\GetOwnPublishedPlansQuery;
use App\ServicePlanning\Application\Query\GetServicePlan\GetServicePlanHandler;
use App\ServicePlanning\Application\Query\GetServicePlan\GetServicePlanQuery;
use App\ServicePlanning\Application\Query\ListServicePlans\ListServicePlansHandler;
use App\ServicePlanning\Application\Query\ListServicePlans\ListServicePlansQuery;
use App\ServicePlanning\Presentation\Http\DTO\PlanPayload;
use App\ServicePlanning\Presentation\Http\DTO\PublishPayload;
use App\Shared\Presentation\Http\WorkflowController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/service-plans', format: 'json')]
final class ServicePlansController extends WorkflowController
{
    public function __construct(
        private readonly ListServicePlansHandler $listServicePlansHandler,
        private readonly GetOwnPublishedPlansHandler $getOwnPublishedPlansHandler,
        private readonly GetServicePlanHandler $getServicePlanHandler,
        private readonly SaveServicePlanHandler $saveServicePlanHandler,
        private readonly PublishServicePlanHandler $publishServicePlanHandler,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->result(fn () => ($this->listServicePlansHandler)(new ListServicePlansQuery()));
    }

    #[Route('/mine', methods: ['GET'], priority: 10)]
    public function mine(): JsonResponse
    {
        return $this->result(fn () => ($this->getOwnPublishedPlansHandler)(new GetOwnPublishedPlansQuery()));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function view(string $id): JsonResponse
    {
        $r = $this->result(fn () => ($this->getServicePlanHandler)(new GetServicePlanQuery($id)));
        $r->headers->set('Cache-Control', 'no-store');

        return $r;
    }

    #[Route('', methods: ['POST'])]
    public function create(
        #[MapRequestPayload]
        PlanPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->saveServicePlanHandler)(new SaveServicePlanCommand($p->occurrenceId, $p->title, $p->items)), 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function edit(
        string $id,
        #[MapRequestPayload]
        PlanPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->saveServicePlanHandler)(new SaveServicePlanCommand($p->occurrenceId, $p->title, $p->items, $id, $p->expectedVersion)));
    }

    #[Route('/{id}/publication', methods: ['PUT'])]
    public function publish(
        string $id,
        #[MapRequestPayload]
        PublishPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->publishServicePlanHandler)(new PublishServicePlanCommand($id, $p->expectedVersion, $p->published)));
    }
}
