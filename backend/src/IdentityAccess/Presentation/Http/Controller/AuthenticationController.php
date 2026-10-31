<?php

declare(strict_types=1);

namespace App\IdentityAccess\Presentation\Http\Controller;

use App\IdentityAccess\Application\Query\GetCurrentAccount\GetCurrentAccountHandler;
use App\IdentityAccess\Application\Query\GetCurrentAccount\GetCurrentAccountQuery;
use App\Shared\Presentation\Http\DTO\ErrorResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class AuthenticationController extends AbstractController
{
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(GetCurrentAccountHandler $handler): JsonResponse
    {
        $account = $handler(new GetCurrentAccountQuery());

        return $this->json($account ?? new ErrorResponse('Authentication required.'), $account ? 200 : 401, headers: ['Cache-Control' => 'private, no-store']);
    }
}
