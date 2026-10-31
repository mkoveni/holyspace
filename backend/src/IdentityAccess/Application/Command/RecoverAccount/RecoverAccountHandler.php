<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\RecoverAccount;

use App\IdentityAccess\Application\Port\AuthenticatedAccount;
use App\IdentityAccess\Application\Port\SecurityStateStore;
use App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateHandler;
use App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateQuery;
use App\IdentityAccess\Domain\Repository\UserAccountRepository;
use App\IdentityAccess\Domain\Service\PasswordHasher;
use App\IdentityAccess\Domain\ValueObject\Username;
use App\Shared\Application\WorkflowTransaction;
use DomainException;
use InvalidArgumentException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class RecoverAccountHandler
{
    public function __construct(
        private SecurityStateStore $db,
        private UserAccountRepository $users,
        private PasswordHasher $passwords,
        private WorkflowTransaction $transactions,
        private ClockInterface $clock,
        private AuthenticatedAccount $tokens,
        private GetAccountSecurityStateHandler $getAccountSecurityStateHandler,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(RecoverAccountCommand $message): void
    {
        $username = $message->username;
        $code = $message->code;
        $password = $message->password;
        $this->validPassword($password);
        $this->transactions->run(function () use ($username, $code, $password) {
            $user = $this->users->findByUsername(Username::fromString($username));
            $invalid = fn () => new DomainException('Recovery details are invalid or no longer available.');
            if (!$user || 'active' !== $user->status()->value) {
                throw $invalid();
            }
            $state = ($this->getAccountSecurityStateHandler)(new GetAccountSecurityStateQuery($user->id()->toString()));
            $hashes = json_decode($state['recovery_hashes'], true, 512, JSON_THROW_ON_ERROR);
            $hash = hash('sha256', strtoupper(trim($code)));
            $found = false;
            foreach ($hashes as $candidate) {
                if (hash_equals($candidate, $hash)) {
                    $found = true;
                }
            }
            if (!$found || (int) $state['recovery_until'] > 0 && (int) $state['recovery_until'] <= $this->clock->now()->getTimestamp()) {
                throw $invalid();
            }
            $user->changePassword($this->passwords->hash($password));
            $this->users->save($user);
            $state['secret'] = null;
            $state['pending_secret'] = null;
            $state['pending_until'] = 0;
            $state['last_step'] = -1;
            $state['recovery_hashes'] = '[]';
            $state['recovery_until'] = 0;
            ++$state['version'];
            $this->save($state);
            $this->audit($state['user_id'], 'account_recovered');
        });
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
