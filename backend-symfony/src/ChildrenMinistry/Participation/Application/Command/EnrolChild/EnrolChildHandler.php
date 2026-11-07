<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application\Command\EnrolChild;

use App\ChildrenMinistry\Participation\Application\Contract\ParticipationReader;
use App\ChildrenMinistry\Participation\Domain\Enrolment;
use App\ChildrenMinistry\Participation\Domain\Repository\ParticipationRepository;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Application\ParticipantAccess;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

final readonly class EnrolChildHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
        private IdGenerator $ids,
        private ParticipationRepository $repository,
        private ParticipationReader $participation,
        private ParticipantAccess $participants,
        private ChurchCalendar $calendar,
    ) {
    }

    /** @return string the new enrolment id */
    public function handle(EnrolChildCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.manage');

        // Person and guard locks come first, before anything that could be contended.
        $this->participants->lockChild($command->childId);
        $now = $this->clock->now();
        if (null === $this->participation->programme($command->programmeId)) {
            throw ChildrenException::missing('Programme');
        }
        ChildrenRules::child($this->participants->available($command->childId)->birthDate, $this->calendar->today());

        $enrolment = Enrolment::create($this->ids->uuid(), $command->programmeId, $command->childId, $command->validFrom, $command->validUntil, $now);
        $this->repository->insertEnrolment($enrolment);
        $this->audit->record($command->actorId, 'child.enrolled', $enrolment->id, '', $now);

        return $enrolment->id;
    }
}
