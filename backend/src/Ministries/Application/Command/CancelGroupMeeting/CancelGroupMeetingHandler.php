<?php

declare(strict_types=1);

namespace App\Ministries\Application\Command\CancelGroupMeeting;

use App\IdentityAccess\Application\Port\AuthenticatedAccount;
use App\Ministries\Domain\Model\GroupMeeting;
use App\Ministries\Domain\Repository\MinistriesRepository;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Security\AccessControl;
use App\Shared\Application\WorkflowTransaction;
use DomainException;
use OutOfBoundsException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class CancelGroupMeetingHandler
{
    public function __construct(
        private MinistriesRepository $records,
        private WorkflowTransaction $transactions,
        private AccessControl $authorization,
        private AuthenticatedAccount $tokens,
        private ClockInterface $clock,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(CancelGroupMeetingCommand $message): object
    {
        $groupId = $message->groupId;
        $id = $message->id;
        $version = $message->version;
        $reason = $message->reason;

        return $this->transactions->run(function () use ($groupId, $id, $version, $reason) {
            $this->group($groupId);
            $this->manager();
            $meeting = $this->meeting($groupId, $id);
            if ($meeting->version !== $version) {
                throw new DomainException('Meeting changed. Reload before retrying.');
            }
            $this->records->save($meeting->cancel($reason));

            return $this->records->find('meetings', $id);
        });
    }

    private function group(string $id): object
    {
        $g = $this->records->find('ministries', $id) ?? throw new OutOfBoundsException('Group not found.');
        if (!$this->allowed($g)) {
            throw new AccessDenied('This group is outside your leadership scope.');
        }

        return $g;
    }

    private function manager(): void
    {
        if (!$this->authorization->isGranted('ministries.manage')) {
            throw new AccessDenied('Group management permission is required.');
        }
    }

    private function meeting(string $groupId, string $id): GroupMeeting
    {
        $m = $this->records->find('meetings', $id);
        if (!$m || $m->ministryId !== $groupId) {
            throw new OutOfBoundsException('Meeting not found for this group.');
        }

        return $m;
    }

    private function allowed(object $group): bool
    {
        if ($this->authorization->isGranted('ministries.manage')) {
            return true;
        }
        $user = $this->tokens->user();
        if (!$group->active || !$this->authorization->isGranted('ministries.lead') || null === $user || 'active' !== $user->status()->value || null === $user->personId()) {
            return false;
        }
        foreach ($this->records->all('memberships') as $m) {
            if ($m->ministryId === $group->id && $m->personId === $user->personId() && $m->active && 'leader' === $m->role && $m->joinedOn <= $this->clock->now()->format('Y-m-d')) {
                return true;
            }
        }

        return false;
    }
}
