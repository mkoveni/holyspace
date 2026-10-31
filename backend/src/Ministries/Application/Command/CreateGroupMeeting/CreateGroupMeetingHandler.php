<?php

declare(strict_types=1);

namespace App\Ministries\Application\Command\CreateGroupMeeting;

use App\Events\Application\ReadModel\OccurrenceDirectory;
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

final readonly class CreateGroupMeetingHandler
{
    public function __construct(
        private MinistriesRepository $records,
        private OccurrenceDirectory $occurrences,
        private WorkflowTransaction $transactions,
        private AccessControl $authorization,
        private AuthenticatedAccount $tokens,
        private ClockInterface $clock,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(CreateGroupMeetingCommand $message): object
    {
        $id = $message->id;
        $occurrenceId = $message->occurrenceId;

        return $this->transactions->run(function () use ($id, $occurrenceId) {
            $group = $this->group($id);
            $this->manager();
            if (!$group->active) {
                throw new DomainException('Archived groups cannot add meetings.');
            }
            $occurrence = $this->occurrences->get($occurrenceId) ?? throw new OutOfBoundsException('Occurrence not found.');
            if ('cancelled' === $occurrence['status']) {
                throw new DomainException('Choose a scheduled occurrence.');
            }
            foreach ($this->records->all('meetings') as $meeting) {
                if ($meeting->occurrenceId === $occurrenceId) {
                    throw new DomainException('This occurrence is already linked to a group meeting. Create a separate occurrence for another group.');
                }
            }
            $meeting = new GroupMeeting($id, $occurrenceId);
            $this->records->save($meeting);

            return $this->records->find('meetings', $meeting->id);
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
