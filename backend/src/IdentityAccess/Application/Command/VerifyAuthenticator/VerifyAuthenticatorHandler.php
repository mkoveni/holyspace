<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\VerifyAuthenticator;

use App\IdentityAccess\Application\Port\AuthenticatedAccount;
use App\IdentityAccess\Application\Port\Authenticator;
use App\IdentityAccess\Application\Port\SecretCipher;
use App\IdentityAccess\Application\Port\SecurityStateStore;
use App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateHandler;
use App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateQuery;
use App\IdentityAccess\Application\Query\GetAuthenticatedAccount\GetAuthenticatedAccountHandler;
use App\IdentityAccess\Application\Query\GetAuthenticatedAccount\GetAuthenticatedAccountQuery;
use App\Shared\Application\WorkflowTransaction;
use DomainException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class VerifyAuthenticatorHandler
{
    public function __construct(
        private SecurityStateStore $db,
        private WorkflowTransaction $transactions,
        private ClockInterface $clock,
        private AuthenticatedAccount $tokens,
        private SecretCipher $cipher,
        private Authenticator $authenticator,
        private GetAuthenticatedAccountHandler $getAuthenticatedAccountHandler,
        private GetAccountSecurityStateHandler $getAccountSecurityStateHandler,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(VerifyAuthenticatorCommand $message): void
    {
        $code = $message->code;
        $this->transactions->run(function () use ($code) {
            $state = ($this->getAccountSecurityStateHandler)(new GetAccountSecurityStateQuery(($this->getAuthenticatedAccountHandler)(new GetAuthenticatedAccountQuery())->id()->toString()));
            if (!$state['secret']) {
                throw new DomainException('Enable an authenticator first.');
            }
            $state['last_step'] = $this->step($state['secret'], $code, (int) $state['last_step']);
            $this->save($state);
            $this->audit($state['user_id'], 'authenticator_verified');
        });
    }

    private function step(string $encrypted, string $code, int $last): int
    {
        return $this->authenticator->step($this->cipher->decrypt($encrypted), $code, $last);
    }

    private function save(array $state): void
    {
        $this->db->save($state);
    }

    private function audit(string $userId, string $action, string $reference = ''): void
    {
        $this->db->audit($userId, $this->tokens->user()?->id()->toString(), $action, $reference, $this->clock->now());
    }
}
