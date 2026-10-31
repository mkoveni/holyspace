<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\ChangeOwnPassword;

use App\IdentityAccess\Application\Port\AuthenticatedAccount;
use App\IdentityAccess\Application\Port\SecurityStateStore;
use App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateHandler;
use App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateQuery;
use App\IdentityAccess\Application\Query\GetAuthenticatedAccount\GetAuthenticatedAccountHandler;
use App\IdentityAccess\Application\Query\GetAuthenticatedAccount\GetAuthenticatedAccountQuery;
use App\IdentityAccess\Application\Query\RequireVerifiedAccount\RequireVerifiedAccountHandler;
use App\IdentityAccess\Application\Query\RequireVerifiedAccount\RequireVerifiedAccountQuery;
use App\IdentityAccess\Domain\Repository\UserAccountRepository;
use App\IdentityAccess\Domain\Service\PasswordHasher;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\WorkflowTransaction;
use DomainException;
use InvalidArgumentException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class ChangeOwnPasswordHandler
{
    public function __construct(
        private SecurityStateStore $db,
        private UserAccountRepository $users,
        private PasswordHasher $passwords,
        private WorkflowTransaction $transactions,
        private ClockInterface $clock,
        private AuthenticatedAccount $tokens,
        private GetAuthenticatedAccountHandler $getAuthenticatedAccountHandler,
        private GetAccountSecurityStateHandler $getAccountSecurityStateHandler,
        private RequireVerifiedAccountHandler $requireVerifiedAccountHandler,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(ChangeOwnPasswordCommand $message): void
    {
        $current = $message->current;
        $replacement = $message->replacement;
        $this->transactions->run(function () use ($current, $replacement) {
            $state = ($this->getAccountSecurityStateHandler)(new GetAccountSecurityStateQuery(($this->getAuthenticatedAccountHandler)(new GetAuthenticatedAccountQuery())->id()->toString()));
            if ($state['secret']) {
                ($this->requireVerifiedAccountHandler)(new RequireVerifiedAccountQuery());
            }
            $this->password($current);
            $this->validPassword($replacement);
            $user = $this->users->findById(($this->getAuthenticatedAccountHandler)(new GetAuthenticatedAccountQuery())->id()) ?? throw new AccessDenied();
            $user->changePassword($this->passwords->hash($replacement));
            $this->users->save($user);
            ++$state['version'];
            $this->save($state);
            $this->audit($state['user_id'], 'password_changed');
        });
    }

    private function password(string $plain): void
    {
        $account = $this->users->findById(($this->getAuthenticatedAccountHandler)(new GetAuthenticatedAccountQuery())->id());
        if (!$account || 'active' !== $account->status()->value || !$this->passwords->verify($plain, $account->passwordHash())) {
            throw new DomainException('Current password is incorrect.');
        }
    }

    private function validPassword(string $password): void
    {
        if (mb_strlen($password) < 12 || mb_strlen($password) > 4096 || '' === trim($password)) {
            throw new InvalidArgumentException('Passwords must contain 12 to 4096 characters.');
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
