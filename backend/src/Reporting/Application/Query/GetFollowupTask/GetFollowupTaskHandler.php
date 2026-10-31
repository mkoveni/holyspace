<?php

declare(strict_types=1);

namespace App\Reporting\Application\Query\GetFollowupTask;

use App\IdentityAccess\Application\Port\AuthenticatedAccount;
use App\People\Application\ReadModel\PersonDirectory;
use App\Reporting\Application\FollowupAccounts;
use App\Reporting\Application\ReadModel\ReportingReadStore;
use App\Reporting\Domain\Model\FollowupTask;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Security\AccessControl;
use DateTimeZone;
use OutOfBoundsException;
use Symfony\Component\Clock\ClockInterface;

final readonly class GetFollowupTaskHandler
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

    public function __invoke(GetFollowupTaskQuery $message): array
    {
        $id = $message->id;
        $this->requirePermissions(['followups.work']);
        $user = $this->userId();
        $t = $this->required('tasks', $id);
        if (!$this->access->isGranted('followups.manage') && $t->assigneeId !== $user) {
            throw new AccessDenied('This task is not assigned to you.');
        }
        $this->requirePermissions($t->requiredPermissions);

        return $this->taskView($t);
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

    private function required(string $kind, string $id): mixed
    {
        return $this->records->find($kind, $id) ?? throw new OutOfBoundsException('Record not found.');
    }

    private function taskView(FollowupTask $t, ?array $people = null, ?array $names = null): array
    {
        return get_object_vars($t) + ['assigneeName' => null === $names ? $this->accounts->name($t->assigneeId) : $names[$t->assigneeId] ?? 'Unavailable account', 'personName' => (null === $people ? $this->people->get($t->personId)['name'] ?? null : $people[$t->personId]['name'] ?? null) ?? 'Unavailable', 'overdue' => !in_array($t->status, ['completed', 'cancelled'], true) && $t->dueOn < $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d')];
    }
}
