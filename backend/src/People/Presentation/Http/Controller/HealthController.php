<?php

declare(strict_types=1);

namespace App\People\Presentation\Http\Controller;

use App\Shared\Presentation\Http\DTO\HealthResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController extends AbstractController
{
    #[Route('/health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return $this->json(new HealthResponse('ok', 'church-management-portal'));
    }
}
