<?php

declare(strict_types=1);

namespace App\People\Presentation\Http\Controller;

use App\Shared\Application\Query\GetReadiness\GetReadinessHandler;
use App\Shared\Application\Query\GetReadiness\GetReadinessQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ReadinessController
{
    #[Route('/health/ready', name: 'app_readiness', methods: ['GET'], format: 'json')]
    public function __invoke(GetReadinessHandler $readiness): JsonResponse
    {
        $checks = $readiness(new GetReadinessQuery());
        $ready = !in_array(false, $checks, true);

        return new JsonResponse(['status' => $ready ? 'ready' : 'unavailable', 'checks' => $checks], $ready ? 200 : 503, ['Cache-Control' => 'no-store']);
    }
}
