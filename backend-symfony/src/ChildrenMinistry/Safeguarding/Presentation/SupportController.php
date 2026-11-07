<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Presentation;

use App\ChildrenMinistry\Safeguarding\Application\Command\AccessChildSupport\AccessChildSupportCommand;
use App\ChildrenMinistry\Safeguarding\Application\Command\PurgeExpiredSupport\PurgeExpiredSupportCommand;
use App\ChildrenMinistry\Safeguarding\Application\Command\SaveChildSupport\SaveChildSupportCommand;
use App\ChildrenMinistry\Safeguarding\Presentation\Request\AccessChildSupportRequest;
use App\ChildrenMinistry\Safeguarding\Presentation\Request\PurgeExpiredSupportRequest;
use App\ChildrenMinistry\Safeguarding\Presentation\Request\SaveChildSupportRequest;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Domain\CalendarDate;
use App\Shared\Presentation\Json;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Confidential care details. Reads are commands so each one is audited; the payload never leaves through any other route. */
#[Route('/api/children/support')]
final readonly class SupportController
{
    public function __construct(private AuthenticatedAccount $account, private CommandBus $commands)
    {
    }

    #[IsGranted('children.manage')]
    #[Route('', methods: ['PUT'])]
    public function save(#[MapRequestPayload] SaveChildSupportRequest $request): Response
    {
        $this->commands->dispatch(new SaveChildSupportCommand(
            $this->account->requireAccount(),
            Json::uuid($request->childId ?? throw new BadRequestException('Child is required.')),
            $request->expectedVersion,
            $request->allergies,
            $request->careInstructions,
            $request->restrictedNotes,
            CalendarDate::parse($request->reviewOn),
            CalendarDate::parse($request->retainUntil),
        ));

        return new Response(status: 204);
    }

    #[IsGranted('children.support')]
    #[Route('/access', methods: ['POST'])]
    public function access(#[MapRequestPayload] AccessChildSupportRequest $request): JsonResponse
    {
        $record = $this->commands->dispatch(new AccessChildSupportCommand(
            $this->account->requireAccount(),
            Json::uuid($request->childId ?? throw new BadRequestException('Child is required.')),
            null === $request->sessionId ? null : Json::uuid($request->sessionId),
        ));

        return new JsonResponse([
            'childId' => $record->childId,
            'allergies' => $record->allergies,
            'careInstructions' => $record->careInstructions,
            'restrictedNotes' => $record->restrictedNotes,
            'reviewOn' => $record->reviewOn->iso(),
            'retainUntil' => $record->retainUntil->iso(),
            'version' => $record->version,
            'updatedAt' => Json::instant($record->updatedAt),
        ]);
    }

    #[IsGranted('children.support')]
    #[Route('/purge', methods: ['POST'])]
    public function purge(#[MapRequestPayload] PurgeExpiredSupportRequest $request): JsonResponse
    {
        return new JsonResponse($this->commands->dispatch(new PurgeExpiredSupportCommand($this->account->requireAccount(), $request->limit)));
    }
}
