<?php

declare(strict_types=1);

namespace App\Payments\Presentation;

use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Payments\Application\Command\CloseWebhookOperation\CloseWebhookOperationCommand;
use App\Payments\Application\Command\RegisterYocoWebhook\RegisterYocoWebhookCommand;
use App\Payments\Application\Command\RegisterYocoWebhook\RegisterYocoWebhookHandler;
use App\Payments\Application\Command\RemoveYocoWebhook\RemoveYocoWebhookCommand;
use App\Payments\Application\Command\RemoveYocoWebhook\RemoveYocoWebhookHandler;
use App\Payments\Application\Query\ListYocoWebhooks\ListYocoWebhooksHandler;
use App\Payments\Application\Query\ListYocoWebhooks\ListYocoWebhooksQuery;
use App\Payments\Presentation\Request\ReasonRequest;
use App\Payments\Presentation\Request\WebhookChangeRequest;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Yoco webhook administration (payments.configure). Signing secrets are never returned. */
#[IsGranted('payments.configure')]
#[Route('/api/payments/settings/yoco', priority: 2)]
final readonly class YocoWebhooksController
{
    public function __construct(
        private CommandBus $commands,
        private AuthenticatedAccount $account,
        private ListYocoWebhooksHandler $list,
        private RegisterYocoWebhookHandler $register,
        private RemoveYocoWebhookHandler $remove,
    ) {
    }

    #[Route('/webhooks', methods: ['GET'])]
    public function list(#[MapQueryParameter] string $mode): JsonResponse
    {
        $response = new JsonResponse(PaymentJson::webhooks($this->list->handle(new ListYocoWebhooksQuery($this->account->requireAccount(), $mode))));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[Route('/webhooks', methods: ['POST'])]
    public function register(#[MapRequestPayload] WebhookChangeRequest $b): JsonResponse
    {
        return new JsonResponse(PaymentJson::change($this->register->handle(new RegisterYocoWebhookCommand(
            $this->account->requireAccount(),
            strtolower((string) $b->requestId),
            (string) $b->mode,
            $b->expectedVersion,
            (string) $b->reason
        ))));
    }

    #[Route('/webhooks/{id}/removal', methods: ['POST'])]
    public function remove(string $id, #[MapRequestPayload] WebhookChangeRequest $b): JsonResponse
    {
        return new JsonResponse(PaymentJson::change($this->remove->handle(new RemoveYocoWebhookCommand(
            $this->account->requireAccount(),
            strtolower((string) $b->requestId),
            (string) $b->mode,
            $b->expectedVersion,
            (string) $b->reason,
            $id
        ))));
    }

    #[Route('/webhook-operations/{id}/close', methods: ['POST'])]
    public function close(#[PathUuid] string $id, #[MapRequestPayload] ReasonRequest $b): Response
    {
        $this->commands->dispatch(new CloseWebhookOperationCommand($this->account->requireAccount(), $id, $b->expectedVersion, (string) $b->reason));

        return new Response(null, 200);
    }
}
