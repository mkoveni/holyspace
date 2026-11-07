<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Command\ReviewWorker;

use App\ChildrenMinistry\Sessions\Domain\Repository\SessionRepository;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Application\ParticipantAccess;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\AccountDirectory;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/** Renews a worker's clearance from now, or revokes it. Renewing needs an adult with an active account. */
final readonly class ReviewWorkerHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
        private SessionRepository $repository,
        private ParticipantAccess $participants,
        private AccountDirectory $accounts,
        private ChurchCalendar $calendar,
    ) {
    }

    public function handle(ReviewWorkerCommand $command): void
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.safeguarding');
        $this->participants->lockChild($command->personId);
        $worker = $this->repository->workerForUpdate($command->personId) ?? throw ChildrenException::missing('Worker');
        ChildrenRules::version($worker->version, $command->expectedVersion);
        $now = $this->clock->now();
        if (!$command->revoked) {
            ChildrenRules::adult($this->participants->available($command->personId)->birthDate, $this->calendar->today());
            if (!$this->accounts->active($worker->accountId)) {
                throw ChildrenException::invalid('Worker account is inactive.');
            }
        }

        $this->repository->updateWorker($worker->review($now, $command->revoked, $command->screenedUntil, $command->trainedUntil, $command->evidenceReference, $command->actorId));
        $this->audit->record($command->actorId, 'worker.reviewed', $worker->personId, '', $now);
    }
}
