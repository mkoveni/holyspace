<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\DisableAccountAndSessions;

use App\IdentityAccess\Application\Port\AuthenticatedAccount;
use App\IdentityAccess\Application\Port\SecurityStateStore;
use App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateHandler;
use App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateQuery;
use App\IdentityAccess\Domain\Exception\UserAccountNotFound;
use App\IdentityAccess\Domain\Repository\UserAccountRepository;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\Shared\Application\WorkflowTransaction;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class DisableAccountAndSessionsHandler
{
    public function __construct(
        private SecurityStateStore $db,
        private UserAccountRepository $users,
        private WorkflowTransaction $transactions,
        private ClockInterface $clock,
        private AuthenticatedAccount $tokens,
        private GetAccountSecurityStateHandler $getAccountSecurityStateHandler,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(DisableAccountAndSessionsCommand $message): void
    {
        $id = $message->id;
        $this->transactions->run(function () use ($id) {
            $user = $this->users->findById(UserId::fromString($id)) ?? throw new UserAccountNotFound($id);
            $user->disable();
            $this->users->save($user);
            $state = ($this->getAccountSecurityStateHandler)(new GetAccountSecurityStateQuery($id));
            ++$state['version'];
            $state['pending_secret'] = null;
            $state['pending_until'] = 0;
            $this->save($state);
            $this->audit($id, 'account_disabled');
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
