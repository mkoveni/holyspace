<?php

declare(strict_types=1);

namespace App\Shared\Presentation;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/** Same path and body shape as the Java Spring Actuator health endpoint. */
final readonly class HealthController
{
    public function __construct(private Connection $connection)
    {
    }

    #[Route('/actuator/health', name: 'health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        try {
            $this->connection->fetchOne('SELECT 1');
        } catch (Throwable) {
            return new JsonResponse(['status' => 'DOWN'], 503);
        }

        return new JsonResponse(['status' => 'UP']);
    }
}
