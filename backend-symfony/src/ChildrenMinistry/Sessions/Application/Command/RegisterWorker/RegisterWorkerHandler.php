<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Command\RegisterWorker;

use App\ChildrenMinistry\Sessions\Domain\Repository\SessionRepository;
use App\ChildrenMinistry\Sessions\Domain\Worker;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Application\ParticipantAccess;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\AccountDirectory;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/** Verifies an adult, with an active account, as cleared to serve with children. A second registration is a 409. */
final readonly class RegisterWorkerHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
        private ParticipantAccess $participants,
        private AccountDirectory $accounts,
        private SessionRepository $repository,
        private ChurchCalendar $calendar,
    ) {
    }

    public function handle(RegisterWorkerCommand $command): void
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.safeguarding');
        $this->participants->lockChild($command->personId);
        $now = $this->clock->now();
        ChildrenRules::adult($this->participants->available($command->personId)->birthDate, $this->calendar->today());
        if (!$this->accounts->active($command->accountId)) {
            throw ChildrenException::invalid('Link an active account to the verified worker.');
        }

        $this->repository->insertWorker(Worker::register($command->personId, $command->accountId, $now, $command->screenedUntil, $command->trainedUntil, $command->evidenceReference, $command->actorId));
        $this->audit->record($command->actorId, 'worker.verified', $command->personId, '', $now);
    }
}
