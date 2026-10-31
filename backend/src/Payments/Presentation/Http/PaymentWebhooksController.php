<?php

declare(strict_types=1);

namespace App\Payments\Presentation\Http;

use App\Payments\Application\Command\ProcessPaymentNotification\ProcessPaymentNotificationCommand;
use App\Payments\Application\Command\ProcessPaymentNotification\ProcessPaymentNotificationHandler;
use DomainException;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class PaymentWebhooksController extends AbstractController
{
    public function __construct(private readonly ProcessPaymentNotificationHandler $processPaymentNotificationHandler)
    {
    }

    #[Route('/api/payments/webhooks/{provider}', requirements: ['provider' => 'payfast|yoco'], methods: ['POST'], format: 'json')]
    public function receive(string $provider, Request $request): JsonResponse
    {
        if (strlen($request->getContent()) > 65536) {
            return new JsonResponse(['received' => false], 413);
        }
        try {
            ($this->processPaymentNotificationHandler)(new ProcessPaymentNotificationCommand($provider, $request->getContent(), ['webhook-id' => $request->headers->get('webhook-id', ''), 'webhook-timestamp' => $request->headers->get('webhook-timestamp', ''), 'webhook-signature' => $request->headers->get('webhook-signature', '')], $request->getClientIp() ?? ''));

            return new JsonResponse(['received' => true]);
        } catch (InvalidArgumentException) {
            return new JsonResponse(['received' => false], 400);
        } catch (DomainException|RuntimeException) {
            return new JsonResponse(['received' => false], 503);
        }
    }
}
