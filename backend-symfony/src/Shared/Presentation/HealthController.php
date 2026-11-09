<?php

declare(strict_types=1);

namespace App\Shared\Presentation;

use App\Shared\Application\HealthCheck;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/** Same path and body shape as the Java Spring Actuator health endpoint. */
final readonly class HealthController
{
    /** @param iterable<HealthCheck> $checks */
    public function __construct(private Connection $connection, private iterable $checks = [])
    {
    }

    #[Route('/actuator/health', name: 'health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        try {
            $this->connection->fetchOne('SELECT 1');
            foreach ($this->checks as $check) {
                if (!$check->healthy()) {
                    return new JsonResponse(['status' => 'DOWN'], 503);
                }
            }
        } catch (Throwable) {
            return new JsonResponse(['status' => 'DOWN'], 503);
        }

        return new JsonResponse(['status' => 'UP']);
    }
}
