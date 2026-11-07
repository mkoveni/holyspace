<?php

declare(strict_types=1);

namespace App\IdentityAccess\Presentation;

use App\IdentityAccess\Application\Command\ChangePassword\ChangePasswordCommand;
use App\IdentityAccess\Application\Command\Login\LoginCommand;
use App\IdentityAccess\Application\Command\Login\LoginHandler;
use App\IdentityAccess\Application\Command\Logout\LogoutCommand;
use App\IdentityAccess\Application\Query\GetAccount\GetAccountHandler;
use App\IdentityAccess\Application\Query\GetAccount\GetAccountQuery;
use App\IdentityAccess\Presentation\Request\LoginRequest;
use App\IdentityAccess\Presentation\Request\PasswordChangeRequest;
use App\Shared\Application\CommandBus;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final readonly class AuthenticationController
{
    public function __construct(
        private CommandBus $commands,
        private Caller $caller,
        private LoginHandler $login,
        private GetAccountHandler $account,
    ) {
    }

    /** Accepts a username or email address and returns an opaque bearer token. Failed attempts are rate limited. */
    #[Route('/api/login_check', methods: ['POST'])]
    public function login(#[MapRequestPayload] LoginRequest $request): JsonResponse
    {
        $result = $this->login->handle(new LoginCommand($request->username, $request->password, $request->deviceName));

        return new JsonResponse(['token' => $result->token]);
    }

    #[Route('/api/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        return new JsonResponse(AccountJson::account($this->account->handle(new GetAccountQuery($this->caller->accountId()))));
    }

    /** Revokes the current session immediately. */
    #[Route('/api/logout', methods: ['POST'])]
    public function logout(): Response
    {
        $this->commands->dispatch(new LogoutCommand($this->caller->accountId(), $this->caller->actor()->sessionId));

        return new Response(status: 204);
    }

    /** Requires the current password and revokes every session, including this one. */
    #[Route('/api/me/password', methods: ['PUT'])]
    public function changePassword(
        #[MapRequestPayload] PasswordChangeRequest $request,
    ): Response {
        $this->commands->dispatch(new ChangePasswordCommand(
            $this->caller->accountId(),
            $this->caller->accountId(),
            $request->currentPassword,
            $request->password,
            false,
            $request->expectedVersion,
        ));

        return new Response(status: 204);
    }
}
