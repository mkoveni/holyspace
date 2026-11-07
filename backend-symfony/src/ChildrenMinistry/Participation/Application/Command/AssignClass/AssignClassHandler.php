<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application\Command\AssignClass;

use App\ChildrenMinistry\Participation\Application\Contract\ParticipationReader;
use App\ChildrenMinistry\Participation\Domain\ClassAssignment;
use App\ChildrenMinistry\Participation\Domain\Repository\ParticipationRepository;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Application\ParticipantAccess;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

final readonly class AssignClassHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
        private IdGenerator $ids,
        private ParticipationRepository $repository,
        private ParticipationReader $participation,
        private ParticipantAccess $participants,
    ) {
    }

    /** @return string the new class assignment id */
    public function handle(AssignClassCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.manage');

        $source = $this->participation->enrolment($command->enrolmentId) ?? throw ChildrenException::missing('Enrolment');
        $this->participants->lockChild($source->childId);
        $enrolment = $this->repository->enrolmentForUpdate($command->enrolmentId) ?? throw ChildrenException::missing('Enrolment');
        $class = $this->repository->findClass($command->classId) ?? throw ChildrenException::missing('MinistryClass');
        if (null !== $enrolment->endedAt
            || $enrolment->programmeId !== $class->programmeId
            || $command->validFrom->isBefore($enrolment->validFrom)
            || $command->validUntil->isAfter($enrolment->validUntil)) {
            throw ChildrenException::invalid('Class assignment must belong to and fit within an active enrolment.');
        }
        $class->requireAge($this->participants->available($enrolment->childId)->birthDate, $command->validFrom);

        $now = $this->clock->now();
        $assignment = ClassAssignment::create($this->ids->uuid(), $class->programmeId, $class->id, $enrolment->childId, $enrolment->id, $command->validFrom, $command->validUntil);
        $this->repository->insertClassAssignment($assignment);
        $this->audit->record($command->actorId, 'class.assigned', $assignment->id, '', $now);

        return $assignment->id;
    }
}
