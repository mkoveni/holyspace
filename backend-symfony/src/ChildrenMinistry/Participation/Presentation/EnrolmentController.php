<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Presentation;

use App\ChildrenMinistry\Participation\Application\AssignmentView;
use App\ChildrenMinistry\Participation\Application\Command\AssignClass\AssignClassCommand;
use App\ChildrenMinistry\Participation\Application\Command\EndClassAssignment\EndClassAssignmentCommand;
use App\ChildrenMinistry\Participation\Application\Command\EndEnrolment\EndEnrolmentCommand;
use App\ChildrenMinistry\Participation\Application\Command\EnrolChild\EnrolChildCommand;
use App\ChildrenMinistry\Participation\Application\EnrolmentView;
use App\ChildrenMinistry\Participation\Application\Query\ListClassAssignments\ListClassAssignmentsHandler;
use App\ChildrenMinistry\Participation\Application\Query\ListClassAssignments\ListClassAssignmentsQuery;
use App\ChildrenMinistry\Participation\Application\Query\ListEnrolments\ListEnrolmentsHandler;
use App\ChildrenMinistry\Participation\Application\Query\ListEnrolments\ListEnrolmentsQuery;
use App\ChildrenMinistry\Participation\Presentation\Request\AssignClassRequest;
use App\ChildrenMinistry\Participation\Presentation\Request\EndClassAssignmentRequest;
use App\ChildrenMinistry\Participation\Presentation\Request\EndEnrolmentRequest;
use App\ChildrenMinistry\Participation\Presentation\Request\EnrolChildRequest;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Domain\CalendarDate;
use App\Shared\Presentation\Json;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Enrolments of children in programmes and their class placements. Reads need children.read, writes children.manage. */
#[Route('/api/children')]
final readonly class EnrolmentController
{
    public function __construct(
        private AuthenticatedAccount $account,
        private CommandBus $commands,
        private ListEnrolmentsHandler $enrolments,
        private ListClassAssignmentsHandler $assignments,
    ) {
    }

    #[IsGranted('children.manage')]
    #[Route('/enrolments', methods: ['POST'])]
    public function enrol(#[MapRequestPayload] EnrolChildRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new EnrolChildCommand(
            $this->account->requireAccount(),
            Json::uuid($request->programmeId ?? throw new BadRequestException('Programme is required.')),
            Json::uuid($request->childId ?? throw new BadRequestException('Child is required.')),
            CalendarDate::parse($request->validFrom),
            CalendarDate::parse($request->validUntil),
        ));

        return new JsonResponse(['id' => $id], 201);
    }

    #[IsGranted('children.manage')]
    #[Route('/class-assignments', methods: ['POST'])]
    public function assign(#[MapRequestPayload] AssignClassRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new AssignClassCommand(
            $this->account->requireAccount(),
            Json::uuid($request->enrolmentId ?? throw new BadRequestException('Enrolment is required.')),
            Json::uuid($request->classId ?? throw new BadRequestException('Class is required.')),
            CalendarDate::parse($request->validFrom),
            CalendarDate::parse($request->validUntil),
        ));

        return new JsonResponse(['id' => $id], 201);
    }

    #[IsGranted('children.manage')]
    #[Route('/enrolments/end', methods: ['POST'])]
    public function endEnrolment(#[MapRequestPayload] EndEnrolmentRequest $request): Response
    {
        $this->commands->dispatch(new EndEnrolmentCommand(
            $this->account->requireAccount(),
            Json::uuid($request->enrolmentId ?? throw new BadRequestException('Enrolment is required.')),
            $request->expectedVersion,
        ));

        return new Response(status: 204);
    }

    #[IsGranted('children.manage')]
    #[Route('/class-assignments/end', methods: ['POST'])]
    public function endAssignment(#[MapRequestPayload] EndClassAssignmentRequest $request): Response
    {
        $this->commands->dispatch(new EndClassAssignmentCommand(
            $this->account->requireAccount(),
            Json::uuid($request->assignmentId ?? throw new BadRequestException('Assignment is required.')),
            $request->expectedVersion,
        ));

        return new Response(status: 204);
    }

    #[IsGranted('children.read')]
    #[Route('/enrolments', methods: ['GET'])]
    public function listEnrolments(
        #[MapQueryParameter(validationFailedStatusCode: 400)] string $childId,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 50,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $enrolments = $this->enrolments->handle(new ListEnrolmentsQuery(Json::uuid($childId), $limit, $offset));

        return new JsonResponse(array_map(static fn (EnrolmentView $enrolment): array => ParticipationJson::enrolment($enrolment), $enrolments));
    }

    #[IsGranted('children.read')]
    #[Route('/class-assignments', methods: ['GET'])]
    public function listAssignments(
        #[MapQueryParameter(validationFailedStatusCode: 400)] string $childId,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 50,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $assignments = $this->assignments->handle(new ListClassAssignmentsQuery(Json::uuid($childId), $limit, $offset));

        return new JsonResponse(array_map(static fn (AssignmentView $assignment): array => ParticipationJson::assignment($assignment), $assignments));
    }
}
