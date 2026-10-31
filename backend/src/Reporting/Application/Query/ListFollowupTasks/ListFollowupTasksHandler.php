<?php

declare(strict_types=1);

namespace App\Reporting\Application\Query\ListFollowupTasks;

use App\IdentityAccess\Application\Port\AuthenticatedAccount;
use App\People\Application\ReadModel\PersonDirectory;
use App\Reporting\Application\FollowupAccounts;
use App\Reporting\Application\ReadModel\ReportingReadStore;
use App\Reporting\Domain\Model\FollowupTask;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Security\AccessControl;
use DateTimeZone;
use Symfony\Component\Clock\ClockInterface;

final readonly class ListFollowupTasksHandler
{
    public function __construct(
        private ReportingReadStore $records,
        private FollowupAccounts $accounts,
        private PersonDirectory $people,
        private AuthenticatedAccount $tokens,
        private AccessControl $access,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ListFollowupTasksQuery $message): array
    {
        $this->requirePermissions(['followups.work']);
        $id = $this->userId();
        $tasks = array_values(array_filter($this->records->all('tasks'), fn ($task) => ($this->access->isGranted('followups.manage') || $task->assigneeId === $id) && $this->allowed($task->requiredPermissions)));
        $people = $this->people->getMany(array_column($tasks, 'personId'));
        $names = $this->accounts->names(array_column($tasks, 'assigneeId'));
        $items = array_map(fn ($task) => $this->taskView($task, $people, $names), $tasks);

        return ['items' => $items];
    }

    private function requirePermissions(array $codes): void
    {
        foreach ($codes as $code) {
            if (!$this->access->isGranted($code)) {
                throw new AccessDenied('Source permissions are required for this report or task.');
            }
        }
    }

    private function userId(): string
    {
        $user = $this->tokens->user();
        if (null === $user || 'active' !== $user->status()->value) {
            throw new AccessDenied('An active staff account is required.');
        }

        return $user->id()->toString();
    }

    private function allowed(array $codes): bool
    {
        foreach ($codes as $code) {
            if (!$this->access->isGranted($code)) {
                return false;
            }
        }

        return true;
    }

    private function taskView(FollowupTask $t, ?array $people = null, ?array $names = null): array
    {
        return get_object_vars($t) + ['assigneeName' => null === $names ? $this->accounts->name($t->assigneeId) : $names[$t->assigneeId] ?? 'Unavailable account', 'personName' => (null === $people ? $this->people->get($t->personId)['name'] ?? null : $people[$t->personId]['name'] ?? null) ?? 'Unavailable', 'overdue' => !in_array($t->status, ['completed', 'cancelled'], true) && $t->dueOn < $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d')];
    }
}
