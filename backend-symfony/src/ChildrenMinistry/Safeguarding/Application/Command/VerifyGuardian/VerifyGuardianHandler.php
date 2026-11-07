<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Command\VerifyGuardian;

use App\ChildrenMinistry\Safeguarding\Domain\GuardianAuthority;
use App\ChildrenMinistry\Safeguarding\Domain\Repository\SafeguardingRepository;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Application\ParticipantAccess;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

final readonly class VerifyGuardianHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
        private IdGenerator $ids,
        private SafeguardingRepository $repository,
        private ParticipantAccess $participants,
        private ChurchCalendar $calendar,
    ) {
    }

    /** @return string the new authority id */
    public function handle(VerifyGuardianCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.safeguarding');

        $this->participants->lockChild($command->childId, $command->guardianId);
        $now = $this->clock->now();
        $today = $this->calendar->today();
        ChildrenRules::child($this->participants->available($command->childId)->birthDate, $today);
        ChildrenRules::adult($this->participants->available($command->guardianId)->birthDate, $today);

        $authority = GuardianAuthority::verify($this->ids->uuid(), $command->childId, $command->guardianId, $command->validFrom, $command->validUntil, $command->evidenceReference, $command->actorId);
        $this->repository->insertGuardianAuthority($authority);
        $this->audit->record($command->actorId, 'guardian.authority.verified', $authority->id, '', $now);

        return $authority->id;
    }
}
