<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application\Command\EndEnrolment;

use App\ChildrenMinistry\Participation\Application\Contract\ParticipationReader;
use App\ChildrenMinistry\Participation\Domain\Repository\ParticipationRepository;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Application\ParticipantAccess;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class EndEnrolmentHandler implements CommandHandler
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

    public function handle(EndEnrolmentCommand $command): void
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.manage');

        $source = $this->participation->enrolment($command->enrolmentId) ?? throw ChildrenException::missing('Enrolment');
        $this->participants->lockChild($source->childId);
        $now = $this->clock->now();
        $enrolment = $this->repository->enrolmentForUpdate($command->enrolmentId) ?? throw ChildrenException::missing('Enrolment');
        $this->repository->updateEnrolment($enrolment->end($command->expectedVersion, $now));
        $this->repository->endAssignments($enrolment->id, $now);
        $this->audit->record($command->actorId, 'enrolment.ended', $enrolment->id, '', $now);
    }
}
