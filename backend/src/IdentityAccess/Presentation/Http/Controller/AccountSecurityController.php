<?php

declare(strict_types=1);

namespace App\IdentityAccess\Presentation\Http\Controller;

use App\IdentityAccess\Application\Command\ChangeOwnPassword\ChangeOwnPasswordCommand;
use App\IdentityAccess\Application\Command\ChangeOwnPassword\ChangeOwnPasswordHandler;
use App\IdentityAccess\Application\Command\EnableAuthenticator\EnableAuthenticatorCommand;
use App\IdentityAccess\Application\Command\EnableAuthenticator\EnableAuthenticatorHandler;
use App\IdentityAccess\Application\Command\RecoverAccount\RecoverAccountCommand;
use App\IdentityAccess\Application\Command\RecoverAccount\RecoverAccountHandler;
use App\IdentityAccess\Application\Command\RegenerateRecoveryCodes\RegenerateRecoveryCodesCommand;
use App\IdentityAccess\Application\Command\RegenerateRecoveryCodes\RegenerateRecoveryCodesHandler;
use App\IdentityAccess\Application\Command\SetupAuthenticator\SetupAuthenticatorCommand;
use App\IdentityAccess\Application\Command\SetupAuthenticator\SetupAuthenticatorHandler;
use App\IdentityAccess\Application\Command\VerifyAuthenticator\VerifyAuthenticatorCommand;
use App\IdentityAccess\Application\Command\VerifyAuthenticator\VerifyAuthenticatorHandler;
use App\IdentityAccess\Application\Port\VerifiedTokenIssuer;
use App\IdentityAccess\Application\Query\GetAccountSecurityStatus\GetAccountSecurityStatusHandler;
use App\IdentityAccess\Application\Query\GetAccountSecurityStatus\GetAccountSecurityStatusQuery;
use App\IdentityAccess\Application\Query\GetAuthenticatedAccount\GetAuthenticatedAccountHandler;
use App\IdentityAccess\Application\Query\GetAuthenticatedAccount\GetAuthenticatedAccountQuery;
use App\IdentityAccess\Presentation\Http\DTO\RecoveryPayload;
use App\IdentityAccess\Presentation\Http\DTO\SecurityPayload;
use App\Shared\Presentation\Http\WorkflowController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class AccountSecurityController extends WorkflowController
{
    public function __construct(
        private readonly VerifiedTokenIssuer $jwt,

        #[Autowire(service: 'limiter.account_security')]
        private readonly RateLimiterFactory $attempts,

        #[Autowire(service: 'limiter.account_recovery')]
        private readonly RateLimiterFactory $recoveryAttempts,

        private readonly GetAccountSecurityStatusHandler $getAccountSecurityStatusHandler,

        private readonly GetAuthenticatedAccountHandler $getAuthenticatedAccountHandler,

        private readonly SetupAuthenticatorHandler $setupAuthenticatorHandler,

        private readonly ChangeOwnPasswordHandler $changeOwnPasswordHandler,

        private readonly EnableAuthenticatorHandler $enableAuthenticatorHandler,

        private readonly RegenerateRecoveryCodesHandler $regenerateRecoveryCodesHandler,

        private readonly VerifyAuthenticatorHandler $verifyAuthenticatorHandler,

        private readonly RecoverAccountHandler $recoverAccountHandler,
    ) {
    }

    #[Route('/api/account-security', methods: ['GET'], format: 'json')]
    public function status(): JsonResponse
    {
        return $this->result(fn () => ($this->getAccountSecurityStatusHandler)(new GetAccountSecurityStatusQuery()));
    }

    #[Route('/api/account-security/{action}', methods: ['POST'], requirements: ['action' => 'setup|enable|verify|recovery-codes|password'], format: 'json')]
    public function action(
        string $action,
        #[MapRequestPayload]
        SecurityPayload $p,
    ): JsonResponse {
        $id = ($this->getAuthenticatedAccountHandler)(new GetAuthenticatedAccountQuery())->id()->toString();
        if (!$this->attempts->create($id)->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException(60, 'Wait before trying another security operation.');
        }

        return $this->result(function () use ($action, $p) {
            if ('setup' === $action) {
                return ($this->setupAuthenticatorHandler)(new SetupAuthenticatorCommand($p->password));
            }
            if ('password' === $action) {
                ($this->changeOwnPasswordHandler)(new ChangeOwnPasswordCommand($p->password, $p->newPassword));

                return ['message' => 'Password changed. Sign in again.'];
            }
            $result = match ($action) {
                'enable' => ($this->enableAuthenticatorHandler)(new EnableAuthenticatorCommand($p->password, $p->code)),
                'recovery-codes' => ($this->regenerateRecoveryCodesHandler)(new RegenerateRecoveryCodesCommand($p->password)),
                'verify' => $this->verify($p->code),
            };

            return $result + ['token' => $this->jwt->issue(($this->getAuthenticatedAccountHandler)(new GetAuthenticatedAccountQuery()))];
        });
    }

    private function verify(string $code): array
    {
        ($this->verifyAuthenticatorHandler)(new VerifyAuthenticatorCommand($code));

        return ['message' => 'Authenticator verified.'];
    }

    #[Route('/api/account-recovery', methods: ['POST'], format: 'json')]
    public function recover(
        Request $request,
        #[MapRequestPayload]
        RecoveryPayload $p,
    ): JsonResponse {
        $ip = $request->getClientIp() ?? 'unknown';
        $ipAllowed = $this->recoveryAttempts->create('ip:'.$ip)->consume()->isAccepted();
        $accountAllowed = $this->recoveryAttempts->create('account:'.hash('sha256', mb_strtolower(trim($p->username))))->consume()->isAccepted();
        if (!$ipAllowed || !$accountAllowed) {
            throw new TooManyRequestsHttpException(900, 'Wait before trying recovery again.');
        }

        return $this->result(function () use ($p) {
            ($this->recoverAccountHandler)(new RecoverAccountCommand($p->username, $p->code, $p->newPassword));

            return ['message' => 'Account recovered. Sign in and enrol an authenticator again. All previous sessions and recovery codes have been revoked.'];
        });
    }

    protected function result(callable $operation, int $status = 200): JsonResponse
    {
        $response = parent::result($operation, $status);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
