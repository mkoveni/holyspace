<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\SetupAuthenticator;

use App\IdentityAccess\Application\Port\AuthenticatedAccount;
use App\IdentityAccess\Application\Port\Authenticator;
use App\IdentityAccess\Application\Port\SecretCipher;
use App\IdentityAccess\Application\Port\SecurityStateStore;
use App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateHandler;
use App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateQuery;
use App\IdentityAccess\Application\Query\GetAuthenticatedAccount\GetAuthenticatedAccountHandler;
use App\IdentityAccess\Application\Query\GetAuthenticatedAccount\GetAuthenticatedAccountQuery;
use App\IdentityAccess\Domain\Repository\UserAccountRepository;
use App\IdentityAccess\Domain\Service\PasswordHasher;
use App\Shared\Application\WorkflowTransaction;
use DomainException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class SetupAuthenticatorHandler
{
    public function __construct(
        private SecurityStateStore $db,
        private UserAccountRepository $users,
        private PasswordHasher $passwords,
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
    public function __invoke(SetupAuthenticatorCommand $message): array
    {
        $password = $message->password;

        return $this->transactions->run(function () use ($password) {
            $this->password($password);
            $user = ($this->getAuthenticatedAccountHandler)(new GetAuthenticatedAccountQuery());
            $state = ($this->getAccountSecurityStateHandler)(new GetAccountSecurityStateQuery($user->id()->toString()));
            if (null !== $state['secret']) {
                throw new DomainException('Authenticator is already enabled.');
            }
            $otp = $this->authenticator->create($user->username()->value());
            $state['pending_secret'] = $this->cipher->encrypt($otp['secret']);
            $state['pending_until'] = $this->clock->now()->getTimestamp() + 600;
            $this->save($state);
            $this->audit($state['user_id'], 'authenticator_setup');

            return ['secret' => $otp['secret'], 'uri' => $otp['uri'], 'expiresInSeconds' => 600];
        });
    }

    private function password(string $plain): void
    {
        $account = $this->users->findById(($this->getAuthenticatedAccountHandler)(new GetAuthenticatedAccountQuery())->id());
        if (!$account || 'active' !== $account->status()->value || !$this->passwords->verify($plain, $account->passwordHash())) {
            throw new DomainException('Current password is incorrect.');
        }
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
