<?php

declare(strict_types=1);

namespace App\Reporting\Presentation\Http;

use App\Reporting\Application\Command\CreateFollowupTask\CreateFollowupTaskCommand;
use App\Reporting\Application\Command\CreateFollowupTask\CreateFollowupTaskHandler;
use App\Reporting\Application\Command\ReassignFollowupTask\ReassignFollowupTaskCommand;
use App\Reporting\Application\Command\ReassignFollowupTask\ReassignFollowupTaskHandler;
use App\Reporting\Application\Command\TransitionFollowupTask\TransitionFollowupTaskCommand;
use App\Reporting\Application\Command\TransitionFollowupTask\TransitionFollowupTaskHandler;
use App\Reporting\Application\Query\GetFollowupTask\GetFollowupTaskHandler;
use App\Reporting\Application\Query\GetFollowupTask\GetFollowupTaskQuery;
use App\Reporting\Application\Query\ListFollowupTasks\ListFollowupTasksHandler;
use App\Reporting\Application\Query\ListFollowupTasks\ListFollowupTasksQuery;
use App\Shared\Presentation\Http\WorkflowController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/followups', format: 'json')]
final class FollowupsController extends WorkflowController
{
    public function __construct(
        private readonly ListFollowupTasksHandler $listFollowupTasksHandler,
        private readonly CreateFollowupTaskHandler $createFollowupTaskHandler,
        private readonly GetFollowupTaskHandler $getFollowupTaskHandler,
        private readonly TransitionFollowupTaskHandler $transitionFollowupTaskHandler,
        private readonly ReassignFollowupTaskHandler $reassignFollowupTaskHandler,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function tasks(): JsonResponse
    {
        return $this->result(fn () => ($this->listFollowupTasksHandler)(new ListFollowupTasksQuery()));
    }

    #[Route('', methods: ['POST'])]
    public function create(
        #[MapRequestPayload]
        TaskPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->createFollowupTaskHandler)(new CreateFollowupTaskCommand($p->personId, $p->assigneeId, $p->title, $p->dueOn, $p->segmentId)), 201);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function task(string $id): JsonResponse
    {
        return $this->result(fn () => ($this->getFollowupTaskHandler)(new GetFollowupTaskQuery($id)));
    }

    #[Route('/{id}/status', methods: ['POST'])]
    public function status(
        string $id,
        #[MapRequestPayload]
        TaskStatusPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->transitionFollowupTaskHandler)(new TransitionFollowupTaskCommand($id, $p->expectedVersion, $p->status, $p->note)));
    }

    #[Route('/{id}/assignment', methods: ['PUT'])]
    public function reassign(
        string $id,
        #[MapRequestPayload]
        TaskAssignmentPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->reassignFollowupTaskHandler)(new ReassignFollowupTaskCommand($id, $p->expectedVersion, $p->assigneeId, $p->dueOn, $p->reason)));
    }

    protected function result(callable $operation, int $status = 200): JsonResponse
    {
        $r = parent::result($operation, $status);
        $r->headers->set('Cache-Control', 'private, no-store');

        return $r;
    }
}
