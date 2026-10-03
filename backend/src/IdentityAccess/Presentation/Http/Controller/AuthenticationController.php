<?php

declare(strict_types=1);

namespace App\IdentityAccess\Presentation\Http\Controller;

use App\IdentityAccess\Infrastructure\Security\SymfonyUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final readonly class AuthenticationController
{
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(#[CurrentUser] ?SymfonyUser $user): JsonResponse
    {
        if ($user === null) {
            return new JsonResponse(['message' => 'Authentication required.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $account = $user->account();

        return new JsonResponse([
            'id' => $account->id()->toString(),
            'username' => $account->username()->value(),
            'personId' => $account->personId(),
            'status' => $account->status()->value,
            'roles' => $user->getRoles(),
            'createdAt' => $account->createdAt()->format(DATE_ATOM),
            'updatedAt' => $account->updatedAt()->format(DATE_ATOM),
        ]);
    }
}
