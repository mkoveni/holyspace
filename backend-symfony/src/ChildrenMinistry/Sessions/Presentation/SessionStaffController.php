<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Presentation;

use App\ChildrenMinistry\Sessions\Application\Command\ImportServingStaff\ImportServingStaffCommand;
use App\ChildrenMinistry\Sessions\Application\Command\SetSessionStaff\SetSessionStaffCommand;
use App\ChildrenMinistry\Sessions\Application\Query\ListServingCandidates\ListServingCandidatesHandler;
use App\ChildrenMinistry\Sessions\Application\Query\ListServingCandidates\ListServingCandidatesQuery;
use App\ChildrenMinistry\Sessions\Application\ServingCandidate;
use App\ChildrenMinistry\Sessions\Domain\StaffRole;
use App\ChildrenMinistry\Sessions\Presentation\Request\ImportServingStaffRequest;
use App\ChildrenMinistry\Sessions\Presentation\Request\SetSessionStaffRequest;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\Json;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Who works each session, including serving assignments imported from Volunteering. Needs children.manage. */
#[Route('/api/children/sessions')]
#[IsGranted('children.manage')]
final readonly class SessionStaffController
{
    public function __construct(
        private AuthenticatedAccount $account,
        private CommandBus $commands,
        private ListServingCandidatesHandler $candidates,
    ) {
    }

    #[Route('/staff', methods: ['PUT'])]
    public function setStaff(#[MapRequestPayload] SetSessionStaffRequest $request): Response
    {
        $this->commands->dispatch(new SetSessionStaffCommand(
            $this->account->requireAccount(),
            Json::uuid($request->sessionId ?? throw new BadRequestException('Session is required.')),
            $request->expectedVersion,
            Json::uuid($request->workerId ?? throw new BadRequestException('Worker is required.')),
            StaffRole::from($request->role ?? throw new BadRequestException('Role is required.')),
            $request->present,
        ));

        return new Response(status: 204);
    }

    #[Route('/{id}/serving-candidates', methods: ['GET'])]
    public function candidates(#[PathUuid] string $id): JsonResponse
    {
        $found = $this->candidates->handle(new ListServingCandidatesQuery($this->account->requireAccount(), $id));

        return new JsonResponse(array_map(static fn (ServingCandidate $candidate): array => [
            'assignment' => [
                'assignmentId' => $candidate->assignment->assignmentId,
                'personId' => $candidate->assignment->personId,
                'name' => $candidate->assignment->name,
                'responsibility' => $candidate->assignment->responsibility,
                'ready' => $candidate->assignment->ready,
                'issues' => $candidate->assignment->issues,
            ],
            'workerRegistered' => $candidate->workerRegistered,
        ], $found));
    }

    #[Route('/{id}/serving-staff', methods: ['POST'])]
    public function import(#[PathUuid] string $id, #[MapRequestPayload] ImportServingStaffRequest $request): Response
    {
        $this->commands->dispatch(new ImportServingStaffCommand(
            $this->account->requireAccount(),
            $id,
            $request->expectedVersion,
            Json::uuid($request->assignmentId ?? throw new BadRequestException('Assignment is required.')),
            $request->responsibility ?? throw new BadRequestException('Responsibility is required.'),
            $request->present,
        ));

        return new Response(status: 204);
    }
}
