<?php

declare(strict_types=1);

namespace App\Communities\Application\Command\UpdateCommunity;

use App\Communities\Application\Contract\CommunityAuditWriter;
use App\Communities\Domain\CommunityException;
use App\Communities\Domain\CommunityRules;
use App\Communities\Domain\Repository\CommunityRepository;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class UpdateCommunityHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private CommunityRepository $communities,
        private CommunityAuditWriter $audit,
        private ClockInterface $clock,
    ) {
    }

    public function handle(UpdateCommunityCommand $command): null
    {
        $reason = CommunityRules::text($command->reason, 2000);
        $parent = $command->parentId === null ? null : $this->communities->lockCommunity($command->parentId);
        if ($command->parentId !== null && ($parent === null || $parent->kind !== 'MINISTRY' || !$parent->active)) {
            throw CommunityException::conflict('Choose an active parent ministry.');
        }
        $community = $this->communities->lockCommunity($command->communityId);
        if ($community === null) {
            throw CommunityException::invalid('The community could not be found.');
        }
        $this->authorization->requireActorPermission($command->actorId, 'ministries.manage');
        CommunityRules::version($community->version, $command->expectedVersion);
        $this->communities->saveCommunity($community->update(
            $command->name,
            $command->description,
            $command->parentId,
            $command->campus,
            $command->active
        ), $command->expectedVersion);
        $this->audit->record(
            $community->id,
            $community->id,
            $command->active ? 'UPDATED' : 'ARCHIVED',
            $reason,
            $command->actorId,
            $this->clock->now()
        );

        return null;
    }
}
