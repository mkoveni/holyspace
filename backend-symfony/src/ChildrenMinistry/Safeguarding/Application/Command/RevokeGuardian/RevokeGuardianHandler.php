<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Command\RevokeGuardian;

use App\ChildrenMinistry\Safeguarding\Application\Contract\SafeguardingReader;
use App\ChildrenMinistry\Safeguarding\Domain\Repository\SafeguardingRepository;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Application\ParticipantAccess;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class RevokeGuardianHandler implements CommandHandler
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

    public function handle(RevokeGuardianCommand $command): void
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.safeguarding');

        // Learn the child unlocked, lock the child, then re-read the row for update.
        $source = $this->safeguarding->guardianAuthority($command->id) ?? throw ChildrenException::missing('GuardianAuthority');
        $this->participants->lockChild($source->childId);
        $now = $this->clock->now();
        $authority = $this->repository->guardianAuthorityForUpdate($command->id) ?? throw ChildrenException::missing('GuardianAuthority');
        $this->repository->updateGuardianAuthority($authority->revoke($command->expectedVersion, $command->reason, $now));
        $this->audit->record($command->actorId, 'guardianauthority.revoked', $authority->id, ChildrenRules::text($command->reason, 500), $now);
    }
}
