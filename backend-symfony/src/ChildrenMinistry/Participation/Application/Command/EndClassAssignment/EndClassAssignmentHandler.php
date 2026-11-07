<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application\Command\EndClassAssignment;

use App\ChildrenMinistry\Participation\Application\Contract\ParticipationReader;
use App\ChildrenMinistry\Participation\Domain\Repository\ParticipationRepository;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Application\ParticipantAccess;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class EndClassAssignmentHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
        private ParticipationRepository $repository,
        private ParticipationReader $participation,
        private ParticipantAccess $participants,
    ) {
    }

    public function handle(EndClassAssignmentCommand $command): void
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.manage');

        $source = $this->participation->classAssignment($command->assignmentId) ?? throw ChildrenException::missing('ClassAssignment');
        $this->participants->lockChild($source->childId);
        $now = $this->clock->now();
        $assignment = $this->repository->classAssignmentForUpdate($command->assignmentId) ?? throw ChildrenException::missing('ClassAssignment');
        $this->repository->updateClassAssignment($assignment->end($command->expectedVersion, $now));
        $this->audit->record($command->actorId, 'classassignment.ended', $assignment->id, '', $now);
    }
}
