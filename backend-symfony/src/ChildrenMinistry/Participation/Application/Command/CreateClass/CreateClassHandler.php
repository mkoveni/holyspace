<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application\Command\CreateClass;

use App\ChildrenMinistry\Participation\Application\Contract\ParticipationReader;
use App\ChildrenMinistry\Participation\Domain\MinistryClass;
use App\ChildrenMinistry\Participation\Domain\Repository\ParticipationRepository;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

final readonly class CreateClassHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
        private IdGenerator $ids,
        private ParticipationRepository $classes,
        private ParticipationReader $participation,
    ) {
    }

    /** @return string the new class id */
    public function handle(CreateClassCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.manage');
        if (null === $this->participation->programme($command->programmeId)) {
            throw ChildrenException::missing('Programme');
        }

        $now = $this->clock->now();
        $class = MinistryClass::create($this->ids->uuid(), $command->programmeId, $command->name, $command->minAgeMonths, $command->maxAgeMonths);
        $this->classes->insertClass($class);
        $this->audit->record($command->actorId, 'class.created', $class->id, '', $now);

        return $class->id;
    }
}
