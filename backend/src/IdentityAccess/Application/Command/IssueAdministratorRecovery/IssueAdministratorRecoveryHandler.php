<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\IssueAdministratorRecovery;

use App\IdentityAccess\Application\Port\AuthenticatedAccount;
use App\IdentityAccess\Application\Port\RecoveryCodeDelivery;
use App\IdentityAccess\Application\Port\SecurityStateStore;
use App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateHandler;
use App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateQuery;
use App\IdentityAccess\Domain\Repository\UserAccountRepository;
use App\IdentityAccess\Domain\ValueObject\Username;
use App\Shared\Application\WorkflowTransaction;
use App\Shared\Domain\ValueObject\Input;
use DomainException;
use OutOfBoundsException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class IssueAdministratorRecoveryHandler
{
    public function __construct(
        private RecoveryCodeDelivery $delivery,
        private SecurityStateStore $db,
        private UserAccountRepository $users,
        private WorkflowTransaction $transactions,
        private ClockInterface $clock,
        private AuthenticatedAccount $tokens,
        private GetAccountSecurityStateHandler $getAccountSecurityStateHandler,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(IssueAdministratorRecoveryCommand $message): void
    {
        $username = $message->username;
        $reference = $message->reference;
        $destination = $message->destination;
        Input::text($reference, 200);
        $this->transactions->run(function () use ($username, $reference, $destination) {
            $user = $this->users->findByUsername(Username::fromString($username)) ?? throw new OutOfBoundsException('Account not found.');
            if ('active' !== $user->status()->value) {
                throw new DomainException('Account must be active.');
            }
            $state = ($this->getAccountSecurityStateHandler)(new GetAccountSecurityStateQuery($user->id()->toString()));
            $code = strtoupper(bin2hex(random_bytes(16)));
            $this->delivery->write($code, $destination);
            $state['recovery_hashes'] = json_encode([hash('sha256', $code)], JSON_THROW_ON_ERROR);
            $state['recovery_until'] = $this->clock->now()->getTimestamp() + 1800;
            ++$state['version'];
            $this->save($state);
            $this->audit($state['user_id'], 'administrator_recovery', $reference);
        });
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
