<?php

declare(strict_types=1);

namespace App\Payments\Presentation;

use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Payments\Application\Command\CheckProviderConnection\CheckProviderConnectionCommand;
use App\Payments\Application\Command\CheckProviderConnection\CheckProviderConnectionHandler;
use App\Payments\Application\Command\SaveProviderSettings\SaveProviderSettingsCommand;
use App\Payments\Application\Query\GetPaymentSettings\GetPaymentSettingsHandler;
use App\Payments\Application\Query\GetPaymentSettings\GetPaymentSettingsQuery;
use App\Payments\Presentation\Request\CheckRequest;
use App\Payments\Presentation\Request\SaveSettingsRequest;
use App\Shared\Application\CommandBus;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Restricted provider settings: payments.configure only (independent of payments.manage). Credentials are write-only. */
#[IsGranted('payments.configure')]
#[Route('/api/payments/settings', priority: 1)]
final readonly class PaymentSettingsController
{
    public function __construct(
        private CommandBus $commands,
        private AuthenticatedAccount $account,
        private GetPaymentSettingsHandler $get,
        private CheckProviderConnectionHandler $check,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function settings(): JsonResponse
    {
        $response = new JsonResponse(PaymentJson::settings($this->get->handle(new GetPaymentSettingsQuery())));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[Route('/{provider}', methods: ['PUT'])]
    public function save(string $provider, #[MapRequestPayload] SaveSettingsRequest $b): Response
    {
        $this->commands->dispatch(new SaveProviderSettingsCommand(
            $this->account->requireAccount(),
            $provider,
            (string) $b->mode,
            $b->expectedVersion,
            $b->enabled,
            $b->monthly,
            $b->publicUrl,
            $b->credentials?->credentials(),
            (string) $b->reason,
            $b->backendUrl
        ));

        return new Response(null, 204);
    }

    #[Route('/{provider}/connection-check', methods: ['POST'])]
    public function check(string $provider, #[MapRequestPayload] CheckRequest $b): JsonResponse
    {
        $response = new JsonResponse(PaymentJson::check($this->check->handle(new CheckProviderConnectionCommand(
            $this->account->requireAccount(),
            $provider,
            (string) $b->mode,
            $b->expectedVersion
        ))));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
