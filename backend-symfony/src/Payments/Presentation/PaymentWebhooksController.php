<?php

declare(strict_types=1);

namespace App\Payments\Presentation;

use App\Payments\Application\Command\ProcessNotification\ProcessNotificationCommand;
use App\Payments\Application\Command\ProcessNotification\ProcessNotificationHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public provider callback (`security.yaml` lets only this POST through without a session). It is an explicit
 * trusted entry point: nothing is written until the gateway authenticated the raw body. Invalid callbacks are 400,
 * transient verification or checkout failures 503 so the provider retries; unknown orders are ignored (204).
 */
final readonly class PaymentWebhooksController
{
    public function __construct(private ProcessNotificationHandler $handler)
    {
    }

    #[Route('/api/payments/webhooks/{provider}', methods: ['POST'], priority: 1)]
    public function receive(string $provider, Request $request): Response
    {
        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $headers[strtolower((string) $name)] = implode(',', array_map('strval', $values));
        }
        $this->handler->handle(new ProcessNotificationCommand($provider, $request->getContent(), $headers, (string) $request->getClientIp()));

        return new Response(null, 204);
    }
}
