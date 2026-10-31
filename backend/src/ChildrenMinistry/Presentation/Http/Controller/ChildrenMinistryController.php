<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Presentation\Http\Controller;

use App\ChildrenMinistry\Application\Command\AuthorisePickup\AuthorisePickupCommand;
use App\ChildrenMinistry\Application\Command\AuthorisePickup\AuthorisePickupHandler;
use App\ChildrenMinistry\Application\Command\CancelMinistrySession\CancelMinistrySessionCommand;
use App\ChildrenMinistry\Application\Command\CancelMinistrySession\CancelMinistrySessionHandler;
use App\ChildrenMinistry\Application\Command\CheckInChild\CheckInChildCommand;
use App\ChildrenMinistry\Application\Command\CheckInChild\CheckInChildHandler;
use App\ChildrenMinistry\Application\Command\CheckOutChild\CheckOutChildCommand;
use App\ChildrenMinistry\Application\Command\CheckOutChild\CheckOutChildHandler;
use App\ChildrenMinistry\Application\Command\CreateProgramme\CreateProgrammeCommand;
use App\ChildrenMinistry\Application\Command\CreateProgramme\CreateProgrammeHandler;
use App\ChildrenMinistry\Application\Command\DeactivateChildRecord\DeactivateChildRecordCommand;
use App\ChildrenMinistry\Application\Command\DeactivateChildRecord\DeactivateChildRecordHandler;
use App\ChildrenMinistry\Application\Command\EnrolChild\EnrolChildCommand;
use App\ChildrenMinistry\Application\Command\EnrolChild\EnrolChildHandler;
use App\ChildrenMinistry\Application\Command\SaveMinistryClass\SaveMinistryClassCommand;
use App\ChildrenMinistry\Application\Command\SaveMinistryClass\SaveMinistryClassHandler;
use App\ChildrenMinistry\Application\Command\SaveMinistrySession\SaveMinistrySessionCommand;
use App\ChildrenMinistry\Application\Command\SaveMinistrySession\SaveMinistrySessionHandler;
use App\ChildrenMinistry\Application\Query\GetChildrenMinistryOverview\GetChildrenMinistryOverviewHandler;
use App\ChildrenMinistry\Application\Query\GetChildrenMinistryOverview\GetChildrenMinistryOverviewQuery;
use App\ChildrenMinistry\Presentation\Http\DTO\AuthorisationPayload;
use App\ChildrenMinistry\Presentation\Http\DTO\CheckInPayload;
use App\ChildrenMinistry\Presentation\Http\DTO\CheckOutPayload;
use App\ChildrenMinistry\Presentation\Http\DTO\ClassPayload;
use App\ChildrenMinistry\Presentation\Http\DTO\CollectionPayload;
use App\ChildrenMinistry\Presentation\Http\DTO\EnrolmentPayload;
use App\ChildrenMinistry\Presentation\Http\DTO\ProgrammePayload;
use App\ChildrenMinistry\Presentation\Http\DTO\SessionPayload;
use App\Shared\Presentation\Http\WorkflowController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/children', format: 'json')]
#[IsGranted('children.manage')]
final class ChildrenMinistryController extends WorkflowController
{
    public function __construct(
        private readonly GetChildrenMinistryOverviewHandler $getChildrenMinistryOverviewHandler,
        private readonly CreateProgrammeHandler $createProgrammeHandler,
        private readonly SaveMinistryClassHandler $saveMinistryClassHandler,
        private readonly EnrolChildHandler $enrolChildHandler,
        private readonly DeactivateChildRecordHandler $deactivateChildRecordHandler,
        private readonly AuthorisePickupHandler $authorisePickupHandler,
        private readonly SaveMinistrySessionHandler $saveMinistrySessionHandler,
        private readonly CancelMinistrySessionHandler $cancelMinistrySessionHandler,
        private readonly CheckInChildHandler $checkInChildHandler,
        private readonly CheckOutChildHandler $checkOutChildHandler,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->result(fn () => ($this->getChildrenMinistryOverviewHandler)(new GetChildrenMinistryOverviewQuery()));
    }

    #[Route('/programmes', methods: ['POST'])]
    public function programme(
        #[MapRequestPayload]
        ProgrammePayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->createProgrammeHandler)(new CreateProgrammeCommand($p->name)), 201);
    }

    #[Route('/classes', methods: ['POST'])]
    public function ministryClass(
        #[MapRequestPayload]
        ClassPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->saveMinistryClassHandler)(new SaveMinistryClassCommand(get_object_vars($p))), 201);
    }

    #[Route('/enrolments', methods: ['POST'])]
    public function enrol(
        #[MapRequestPayload]
        EnrolmentPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->enrolChildHandler)(new EnrolChildCommand(get_object_vars($p))), 201);
    }

    #[Route('/enrolments/{id}/deactivate', methods: ['POST'])]
    public function deactivate(string $id): JsonResponse
    {
        return $this->result(fn () => ($this->deactivateChildRecordHandler)(new DeactivateChildRecordCommand('enrolments', $id)));
    }

    #[Route('/authorisations', methods: ['POST'])]
    public function authorise(
        #[MapRequestPayload]
        AuthorisationPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->authorisePickupHandler)(new AuthorisePickupCommand(get_object_vars($p))), 201);
    }

    #[Route('/authorisations/{id}/revoke', methods: ['POST'])]
    public function revoke(string $id): JsonResponse
    {
        return $this->result(fn () => ($this->deactivateChildRecordHandler)(new DeactivateChildRecordCommand('authorisations', $id)));
    }

    #[Route('/sessions', methods: ['POST'])]
    public function session(
        #[MapRequestPayload]
        SessionPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->saveMinistrySessionHandler)(new SaveMinistrySessionCommand(get_object_vars($p))), 201);
    }

    #[Route('/sessions/{id}', methods: ['PUT'])]
    public function review(
        string $id,
        #[MapRequestPayload]
        SessionPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->saveMinistrySessionHandler)(new SaveMinistrySessionCommand(get_object_vars($p), $id)));
    }

    #[Route('/sessions/{id}/cancel', methods: ['POST'])]
    public function cancel(string $id): JsonResponse
    {
        return $this->result(fn () => ($this->cancelMinistrySessionHandler)(new CancelMinistrySessionCommand($id)));
    }

    #[Route('/checkins', methods: ['POST'])]
    public function checkIn(
        #[MapRequestPayload]
        CheckInPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->checkInChildHandler)(new CheckInChildCommand(get_object_vars($p))), 201);
    }

    #[Route('/checkout', methods: ['POST'])]
    public function collect(
        #[MapRequestPayload]
        CollectionPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->checkOutChildHandler)(new CheckOutChildCommand($p->checkInId, get_object_vars($p))));
    }

    #[Route('/checkins/{id}/checkout', methods: ['POST'])]
    public function checkOut(
        string $id,
        #[MapRequestPayload]
        CheckOutPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->checkOutChildHandler)(new CheckOutChildCommand($id, get_object_vars($p))));
    }
}
