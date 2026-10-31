<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\EnableAuthenticator;

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

final readonly class EnableAuthenticatorHandler
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
    public function __invoke(EnableAuthenticatorCommand $message): array
    {
        $password = $message->password;
        $code = $message->code;

        return $this->transactions->run(function () use ($password, $code) {
            $this->password($password);
            $state = ($this->getAccountSecurityStateHandler)(new GetAccountSecurityStateQuery(($this->getAuthenticatedAccountHandler)(new GetAuthenticatedAccountQuery())->id()->toString()));
            if (null !== $state['secret'] || !$state['pending_secret'] || $state['pending_until'] <= $this->clock->now()->getTimestamp()) {
                throw new DomainException('Start a new authenticator setup.');
            }
            $step = $this->step($state['pending_secret'], $code, -1);
            $codes = $this->codes();
            $state['secret'] = $state['pending_secret'];
            $state['pending_secret'] = null;
            $state['pending_until'] = 0;
            $state['last_step'] = $step;
            $state['recovery_hashes'] = json_encode(array_map(fn ($c) => hash('sha256', $c), $codes), JSON_THROW_ON_ERROR);
            $state['recovery_until'] = 0;
            ++$state['version'];
            $this->save($state);
            $this->audit($state['user_id'], 'authenticator_enabled');

            return ['recoveryCodes' => $codes];
        });
    }

    private function password(string $plain): void
    {
        $account = $this->users->findById(($this->getAuthenticatedAccountHandler)(new GetAuthenticatedAccountQuery())->id());
        if (!$account || 'active' !== $account->status()->value || !$this->passwords->verify($plain, $account->passwordHash())) {
            throw new DomainException('Current password is incorrect.');
        }
    }

    private function step(string $encrypted, string $code, int $last): int
    {
        return $this->authenticator->step($this->cipher->decrypt($encrypted), $code, $last);
    }

    private function codes(): array
    {
        return array_map(fn () => strtoupper(bin2hex(random_bytes(16))), range(1, 8));
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
