<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Command\RevokePickupAuthorization;

use App\ChildrenMinistry\Safeguarding\Application\Contract\SafeguardingReader;
use App\ChildrenMinistry\Safeguarding\Domain\Repository\SafeguardingRepository;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Application\ParticipantAccess;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class RevokePickupAuthorizationHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
        private SafeguardingRepository $repository,
        private SafeguardingReader $safeguarding,
        private ParticipantAccess $participants,
    ) {
    }

    public function handle(RevokePickupAuthorizationCommand $command): void
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.safeguarding');

        $source = $this->safeguarding->pickupAuthorization($command->id) ?? throw ChildrenException::missing('PickupAuthorization');
        $this->participants->lockChild($source->childId);
        $now = $this->clock->now();
        $grant = $this->repository->pickupAuthorizationForUpdate($command->id) ?? throw ChildrenException::missing('PickupAuthorization');
        $this->repository->updatePickupAuthorization($grant->revoke($command->expectedVersion, $command->reason, $now));
        $this->audit->record($command->actorId, 'pickupauthorization.revoked', $grant->id, ChildrenRules::text($command->reason, 500), $now);
    }
}
