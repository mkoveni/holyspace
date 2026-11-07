<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Command\AuthorizePickup;

use App\ChildrenMinistry\Participation\Application\Contract\ParticipationReader;
use App\ChildrenMinistry\Safeguarding\Domain\PickupAuthorization;
use App\ChildrenMinistry\Safeguarding\Domain\Repository\SafeguardingRepository;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Application\ParticipantAccess;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

final readonly class AuthorizePickupHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
        private IdGenerator $ids,
        private SafeguardingRepository $repository,
        private ParticipationReader $participation,
        private ParticipantAccess $participants,
        private ChurchCalendar $calendar,
    ) {
    }

    /** @return string the new grant id */
    public function handle(AuthorizePickupCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.safeguarding');

        $this->participants->lockChild($command->childId, $command->collectorId);
        $now = $this->clock->now();
        $today = $this->calendar->today();
        ChildrenRules::child($this->participants->available($command->childId)->birthDate, $today);
        ChildrenRules::adult($this->participants->available($command->collectorId)->birthDate, $today);
        if (null === $this->participation->programme($command->programmeId)) {
            throw ChildrenException::missing('Programme');
        }
        $authority = $this->repository->findGuardianAuthority($command->authorityId) ?? throw ChildrenException::missing('GuardianAuthority');

        $grant = PickupAuthorization::grant($this->ids->uuid(), $command->programmeId, $command->childId, $command->collectorId, $authority, $command->validFrom, $command->validUntil, $command->evidenceReference, $command->actorId);
        $this->repository->insertPickupAuthorization($grant);
        $this->audit->record($command->actorId, 'pickup.authorized', $grant->id, '', $now);

        return $grant->id;
    }
}
