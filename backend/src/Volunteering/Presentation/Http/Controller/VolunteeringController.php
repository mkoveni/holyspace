<?php

declare(strict_types=1);

namespace App\Volunteering\Presentation\Http\Controller;

use App\Shared\Presentation\Http\WorkflowController;
use App\Volunteering\Application\Command\AssignVolunteer\AssignVolunteerCommand;
use App\Volunteering\Application\Command\AssignVolunteer\AssignVolunteerHandler;
use App\Volunteering\Application\Command\ChangeAssignmentStatus\ChangeAssignmentStatusCommand;
use App\Volunteering\Application\Command\ChangeAssignmentStatus\ChangeAssignmentStatusHandler;
use App\Volunteering\Application\Command\SaveVolunteerProfile\SaveVolunteerProfileCommand;
use App\Volunteering\Application\Command\SaveVolunteerProfile\SaveVolunteerProfileHandler;
use App\Volunteering\Application\Command\SaveVolunteerRole\SaveVolunteerRoleCommand;
use App\Volunteering\Application\Command\SaveVolunteerRole\SaveVolunteerRoleHandler;
use App\Volunteering\Application\Command\SaveVolunteerShift\SaveVolunteerShiftCommand;
use App\Volunteering\Application\Command\SaveVolunteerShift\SaveVolunteerShiftHandler;
use App\Volunteering\Application\Query\GetVolunteeringOverview\GetVolunteeringOverviewHandler;
use App\Volunteering\Application\Query\GetVolunteeringOverview\GetVolunteeringOverviewQuery;
use App\Volunteering\Presentation\Http\DTO\AssignmentPayload;
use App\Volunteering\Presentation\Http\DTO\AssignmentStatusPayload;
use App\Volunteering\Presentation\Http\DTO\ProfilePayload;
use App\Volunteering\Presentation\Http\DTO\RolePayload;
use App\Volunteering\Presentation\Http\DTO\ShiftPayload;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/volunteering', format: 'json')]
#[IsGranted('volunteering.manage')]
final class VolunteeringController extends WorkflowController
{
    public function __construct(
        private readonly GetVolunteeringOverviewHandler $getVolunteeringOverviewHandler,
        private readonly SaveVolunteerRoleHandler $saveVolunteerRoleHandler,
        private readonly SaveVolunteerProfileHandler $saveVolunteerProfileHandler,
        private readonly SaveVolunteerShiftHandler $saveVolunteerShiftHandler,
        private readonly AssignVolunteerHandler $assignVolunteerHandler,
        private readonly ChangeAssignmentStatusHandler $changeAssignmentStatusHandler,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->result(fn () => ($this->getVolunteeringOverviewHandler)(new GetVolunteeringOverviewQuery()));
    }

    #[Route('/roles', methods: ['POST'])]
    public function role(
        #[MapRequestPayload]
        RolePayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->saveVolunteerRoleHandler)(new SaveVolunteerRoleCommand(get_object_vars($p))), 201);
    }

    #[Route('/profiles', methods: ['POST'])]
    public function profile(
        #[MapRequestPayload]
        ProfilePayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->saveVolunteerProfileHandler)(new SaveVolunteerProfileCommand(get_object_vars($p))), 201);
    }

    #[Route('/profiles/{id}', methods: ['PUT'])]
    public function updateProfile(
        string $id,
        #[MapRequestPayload]
        ProfilePayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->saveVolunteerProfileHandler)(new SaveVolunteerProfileCommand(get_object_vars($p), $id)));
    }

    #[Route('/shifts', methods: ['POST'])]
    public function shift(
        #[MapRequestPayload]
        ShiftPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->saveVolunteerShiftHandler)(new SaveVolunteerShiftCommand(get_object_vars($p))), 201);
    }

    #[Route('/shifts/{id}', methods: ['PUT'])]
    public function updateShift(
        string $id,
        #[MapRequestPayload]
        ShiftPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->saveVolunteerShiftHandler)(new SaveVolunteerShiftCommand(get_object_vars($p), $id)));
    }

    #[Route('/assignments', methods: ['POST'])]
    public function assign(
        #[MapRequestPayload]
        AssignmentPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->assignVolunteerHandler)(new AssignVolunteerCommand(get_object_vars($p))), 201);
    }

    #[Route('/assignments/{id}/status', methods: ['POST'])]
    public function assignmentStatus(
        string $id,
        #[MapRequestPayload]
        AssignmentStatusPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->changeAssignmentStatusHandler)(new ChangeAssignmentStatusCommand($id, $p->status)));
    }
}
