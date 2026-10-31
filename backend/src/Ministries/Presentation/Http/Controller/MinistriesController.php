<?php

declare(strict_types=1);

namespace App\Ministries\Presentation\Http\Controller;

use App\Ministries\Application\Command\ArchiveMinistry\ArchiveMinistryCommand;
use App\Ministries\Application\Command\ArchiveMinistry\ArchiveMinistryHandler;
use App\Ministries\Application\Command\ChangeMinistryRole\ChangeMinistryRoleCommand;
use App\Ministries\Application\Command\ChangeMinistryRole\ChangeMinistryRoleHandler;
use App\Ministries\Application\Command\JoinMinistry\JoinMinistryCommand;
use App\Ministries\Application\Command\JoinMinistry\JoinMinistryHandler;
use App\Ministries\Application\Command\LeaveMinistry\LeaveMinistryCommand;
use App\Ministries\Application\Command\LeaveMinistry\LeaveMinistryHandler;
use App\Ministries\Application\Command\SaveMinistry\SaveMinistryCommand;
use App\Ministries\Application\Command\SaveMinistry\SaveMinistryHandler;
use App\Ministries\Application\Query\GetMinistries\GetMinistriesHandler;
use App\Ministries\Application\Query\GetMinistries\GetMinistriesQuery;
use App\Ministries\Presentation\Http\DTO\LeavePayload;
use App\Ministries\Presentation\Http\DTO\MembershipPayload;
use App\Ministries\Presentation\Http\DTO\MembershipRolePayload;
use App\Ministries\Presentation\Http\DTO\MinistryPayload;
use App\Shared\Presentation\Http\WorkflowController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/ministries', format: 'json')]
#[IsGranted('ministries.manage')]
final class MinistriesController extends WorkflowController
{
    public function __construct(
        private readonly GetMinistriesHandler $getMinistriesHandler,
        private readonly SaveMinistryHandler $saveMinistryHandler,
        private readonly ArchiveMinistryHandler $archiveMinistryHandler,
        private readonly JoinMinistryHandler $joinMinistryHandler,
        private readonly ChangeMinistryRoleHandler $changeMinistryRoleHandler,
        private readonly LeaveMinistryHandler $leaveMinistryHandler,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->result(fn () => ($this->getMinistriesHandler)(new GetMinistriesQuery()));
    }

    #[Route('/ministries', methods: ['POST'])]
    public function ministry(
        #[MapRequestPayload]
        MinistryPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->saveMinistryHandler)(new SaveMinistryCommand(get_object_vars($p))), 201);
    }

    #[Route('/ministries/{id}/archive', methods: ['POST'])]
    public function archive(string $id): JsonResponse
    {
        return $this->result(fn () => ($this->archiveMinistryHandler)(new ArchiveMinistryCommand($id)));
    }

    #[Route('/memberships', methods: ['POST'])]
    public function join(
        #[MapRequestPayload]
        MembershipPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->joinMinistryHandler)(new JoinMinistryCommand(get_object_vars($p))), 201);
    }

    #[Route('/memberships/{id}/role', methods: ['POST'])]
    public function role(
        string $id,
        #[MapRequestPayload]
        MembershipRolePayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->changeMinistryRoleHandler)(new ChangeMinistryRoleCommand($id, $p->role)));
    }

    #[Route('/memberships/{id}/leave', methods: ['POST'])]
    public function leave(
        string $id,
        #[MapRequestPayload]
        LeavePayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->leaveMinistryHandler)(new LeaveMinistryCommand($id, $p->leftOn)));
    }
}
