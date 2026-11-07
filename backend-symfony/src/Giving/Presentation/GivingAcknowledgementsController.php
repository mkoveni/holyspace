<?php

declare(strict_types=1);

namespace App\Giving\Presentation;

use App\Giving\Application\Command\IssueAcknowledgement\IssueAcknowledgementCommand;
use App\Giving\Application\Contract\AcknowledgementView;
use App\Giving\Application\Query\GetAcknowledgement\GetAcknowledgementHandler;
use App\Giving\Application\Query\GetAcknowledgement\GetAcknowledgementQuery;
use App\Giving\Application\Query\GetGiftAcknowledgement\GetGiftAcknowledgementHandler;
use App\Giving\Application\Query\GetGiftAcknowledgement\GetGiftAcknowledgementQuery;
use App\Giving\Domain\AcknowledgementLine;
use App\Giving\Presentation\Request\IssueAcknowledgementRequest;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Numbered gift acknowledgements. Issuing needs giving.acknowledge; reads need giving.read (implied by giving.manage)
 * and never issue or reissue one.
 */
#[Route('/api/giving')]
final readonly class GivingAcknowledgementsController
{
    public function __construct(
        private CommandBus $commands,
        private AuthenticatedAccount $account,
        private GetGiftAcknowledgementHandler $forGift,
        private GetAcknowledgementHandler $get,
    ) {
    }

    #[IsGranted('giving.acknowledge')]
    #[Route('/gifts/{giftId}/acknowledgements', methods: ['POST'])]
    public function issue(#[PathUuid] string $giftId, #[MapRequestPayload] IssueAcknowledgementRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new IssueAcknowledgementCommand(
            $this->account->requireAccount(),
            strtolower((string) $request->requestId),
            $giftId,
        ));

        return new JsonResponse(['id' => $id], 201);
    }

    #[IsGranted('giving.read')]
    #[Route('/gifts/{giftId}/acknowledgement', methods: ['GET'])]
    public function forGift(#[PathUuid] string $giftId): JsonResponse
    {
        $acknowledgement = $this->forGift->handle(new GetGiftAcknowledgementQuery($giftId));

        return new JsonResponse(['acknowledgement' => null === $acknowledgement ? null : self::view($acknowledgement)]);
    }

    #[IsGranted('giving.read')]
    #[Route('/acknowledgements/{acknowledgementId}', methods: ['GET'])]
    public function get(#[PathUuid] string $acknowledgementId): JsonResponse
    {
        return new JsonResponse(self::view($this->get->handle(new GetAcknowledgementQuery($acknowledgementId))));
    }

    /** @return array<string, mixed> */
    private static function view(AcknowledgementView $view): array
    {
        return [
            'id' => $view->id,
            'giftId' => $view->giftId,
            'number' => $view->number,
            'donorName' => $view->donorName,
            'issuedOn' => $view->issuedOn->iso(),
            'receivedOn' => $view->receivedOn->iso(),
            'amountMinor' => $view->amountMinor,
            'currency' => $view->currency,
            'method' => $view->method,
            'reference' => $view->reference,
            'voided' => $view->voided,
            'reversedOn' => $view->reversedOn?->iso(),
            'reversalReason' => $view->reversalReason,
            'lines' => array_map(static fn (AcknowledgementLine $line): array => [
                'fundId' => $line->fundId,
                'fundName' => $line->fundName,
                'amountMinor' => $line->amountMinor,
            ], $view->lines),
        ];
    }
}
