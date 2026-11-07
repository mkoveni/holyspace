<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application\Command\CreateProgramme;

use App\ChildrenMinistry\Participation\Domain\Programme;
use App\ChildrenMinistry\Participation\Domain\Repository\ParticipationRepository;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

final readonly class CreateProgrammeHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
        private IdGenerator $ids,
        private ParticipationRepository $programmes,
    ) {
    }

    /** @return string the new programme id */
    public function handle(CreateProgrammeCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.manage');

        $now = $this->clock->now();
        $programme = Programme::create($this->ids->uuid(), $command->name, $command->minStaff, $command->childrenPerStaff, $now);
        $this->programmes->insertProgramme($programme);
        $this->audit->record($command->actorId, 'programme.created', $programme->id, '', $now);

        return $programme->id;
    }
}
