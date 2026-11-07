<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/** The 401 and 403 bodies the frontend already expects from the Java API. */
final class JsonSecurityResponses implements AuthenticationEntryPointInterface, AuthenticationFailureHandlerInterface, AccessDeniedHandlerInterface
{
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return $this->unauthenticated();
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return $this->unauthenticated();
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): Response
    {
        return new JsonResponse(['message' => 'Permission denied.'], 403);
    }

    private function unauthenticated(): JsonResponse
    {
        return new JsonResponse(['message' => 'Authentication required.'], 401, ['WWW-Authenticate' => 'Bearer']);
    }
}
