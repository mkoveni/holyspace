<?php

declare(strict_types=1);

namespace App\Reporting\Application\Command\ReassignFollowupTask;

use App\IdentityAccess\Application\Port\AuthenticatedAccount;
use App\Reporting\Application\FollowupAccounts;
use App\Reporting\Application\Query\GetFollowupTask\GetFollowupTaskHandler;
use App\Reporting\Application\Query\GetFollowupTask\GetFollowupTaskQuery;
use App\Reporting\Domain\Repository\ReportingRepository;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Security\AccessControl;
use App\Shared\Application\WorkflowTransaction;
use DomainException;
use OutOfBoundsException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class ReassignFollowupTaskHandler
{
    public function __construct(
        private ReportingRepository $records,
        private FollowupAccounts $accounts,
        private WorkflowTransaction $transactions,
        private AuthenticatedAccount $tokens,
        private AccessControl $access,
        private ClockInterface $clock,
        private GetFollowupTaskHandler $getFollowupTaskHandler,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(ReassignFollowupTaskCommand $message): array
    {
        $id = $message->id;
        $version = $message->version;
        $assigneeId = $message->assigneeId;
        $dueOn = $message->dueOn;
        $reason = $message->reason;
        $this->requirePermissions(['followups.manage']);

        return $this->transactions->run(function () use ($id, $version, $assigneeId, $dueOn, $reason) {
            ($this->getFollowupTaskHandler)(new GetFollowupTaskQuery($id));
            $t = $this->required('tasks', $id);
            if ($t->version !== $version) {
                throw new DomainException('Task changed. Reload before updating.');
            }
            if (!$this->accounts->eligible($assigneeId, ['followups.work', ...$t->requiredPermissions])) {
                throw new DomainException('Assignee no longer has required access.');
            }
            $this->records->save($t->reassign($assigneeId, $dueOn, $reason, $this->userId(), $this->clock->now()->format(DATE_ATOM)));

            return ($this->getFollowupTaskHandler)(new GetFollowupTaskQuery($id));
        });
    }

    private function requirePermissions(array $codes): void
    {
        foreach ($codes as $code) {
            if (!$this->access->isGranted($code)) {
                throw new AccessDenied('Source permissions are required for this report or task.');
            }
        }
    }

    private function required(string $kind, string $id): mixed
    {
        return $this->records->find($kind, $id) ?? throw new OutOfBoundsException('Record not found.');
    }

    private function userId(): string
    {
        $user = $this->tokens->user();
        if (null === $user || 'active' !== $user->status()->value) {
            throw new AccessDenied('An active staff account is required.');
        }

        return $user->id()->toString();
    }
}
