<?php

declare(strict_types=1);

namespace App\IdentityAccess\Presentation;

use App\IdentityAccess\Application\AccountView;
use App\IdentityAccess\Application\Command\ChangePassword\ChangePasswordCommand;
use App\IdentityAccess\Application\Command\CreateAccount\CreateAccountCommand;
use App\IdentityAccess\Application\Command\SetAccountRole\SetAccountRoleCommand;
use App\IdentityAccess\Application\Command\SetAccountStatus\SetAccountStatusCommand;
use App\IdentityAccess\Application\Command\UpdateAccount\UpdateAccountCommand;
use App\IdentityAccess\Application\Query\GetAccount\GetAccountHandler;
use App\IdentityAccess\Application\Query\GetAccount\GetAccountQuery;
use App\IdentityAccess\Application\Query\ListAccounts\ListAccountsHandler;
use App\IdentityAccess\Application\Query\ListAccounts\ListAccountsQuery;
use App\IdentityAccess\Presentation\Request\CreateAccountRequest;
use App\IdentityAccess\Presentation\Request\PasswordResetRequest;
use App\IdentityAccess\Presentation\Request\ProfileRequest;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Account administration. Every operation requires identity.manage. */
#[IsGranted('identity.manage')]
#[Route('/api/users')]
final readonly class AccountController
{
    public function __construct(
        private CommandBus $commands,
        private Caller $caller,
        private GetAccountHandler $get,
        private ListAccountsHandler $list,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(
        #[MapQueryParameter] string $search = '',
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, options: ['min_range' => 1, 'max_range' => 100], validationFailedStatusCode: 400)] int $limit = 50,
    ): JsonResponse {
        if (mb_strlen($search) > 254) {
            throw new BadRequestException('Search is too long.');
        }
        $accounts = $this->list->handle(new ListAccountsQuery($search, $limit));

        return new JsonResponse(['items' => array_map(AccountJson::account(...), $accounts)]);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(#[PathUuid] string $id): JsonResponse
    {
        return new JsonResponse(AccountJson::account($this->view($id)));
    }

    /** Effective permissions, including those implied by management grants. */
    #[Route('/{id}/permissions', methods: ['GET'])]
    public function permissions(#[PathUuid] string $id): JsonResponse
    {
        return new JsonResponse($this->view($id)->permissions);
    }

    #[Route('', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateAccountRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch(new CreateAccountCommand(
            $this->caller->accountId(),
            $request->username,
            $request->email,
            $request->displayName,
            $request->password,
        ));

        return new JsonResponse(['id' => $id], 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(#[PathUuid] string $id, #[MapRequestPayload] ProfileRequest $request): Response
    {
        $this->commands->dispatch(new UpdateAccountCommand($this->caller->accountId(), $id, $request->email, $request->displayName, $request->expectedVersion));

        return new Response(status: 204);
    }

    /** Administrator reset. Revokes all sessions for the account. */
    #[Route('/{id}/password', methods: ['PUT'])]
    public function password(#[PathUuid] string $id, #[MapRequestPayload] PasswordResetRequest $request): Response
    {
        $this->commands->dispatch(new ChangePasswordCommand($this->caller->accountId(), $id, null, $request->password, true, $request->expectedVersion));

        return new Response(status: 204);
    }

    /** Revokes sessions. The last active account manager cannot be disabled. */
    #[Route('/{id}/disable', methods: ['POST'])]
    public function disable(#[PathUuid] string $id): Response
    {
        $this->commands->dispatch(new SetAccountStatusCommand($this->caller->accountId(), $id, false));

        return new Response(status: 204);
    }

    /** Re-enables the account. Previously revoked sessions remain invalid. */
    #[Route('/{id}/enable', methods: ['POST'])]
    public function enable(#[PathUuid] string $id): Response
    {
        $this->commands->dispatch(new SetAccountStatusCommand($this->caller->accountId(), $id, true));

        return new Response(status: 204);
    }

    #[Route('/{id}/roles/{roleId}', methods: ['POST'])]
    public function assign(#[PathUuid] string $id, #[PathUuid] string $roleId): Response
    {
        $this->commands->dispatch(new SetAccountRoleCommand($this->caller->accountId(), $id, $roleId, true));

        return new Response(status: 204);
    }

    /** At least one active account manager must remain. */
    #[Route('/{id}/roles/{roleId}', methods: ['DELETE'])]
    public function remove(#[PathUuid] string $id, #[PathUuid] string $roleId): Response
    {
        $this->commands->dispatch(new SetAccountRoleCommand($this->caller->accountId(), $id, $roleId, false));

        return new Response(status: 204);
    }

    private function view(string $id): AccountView
    {
        return $this->get->handle(new GetAccountQuery($id));
    }
}
